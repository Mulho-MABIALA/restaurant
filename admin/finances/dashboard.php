<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financier - Restaurant</title>
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
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Financier</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="dashboard.php" class="nav-btn active bg-blue-600 text-white px-4 py-2 rounded-lg">Tableau de bord</a>
                        <a href="facturation.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Facturation</a>
                        <a href="rapports.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Rapports</a>
                        <a href="tresorerie.php" class="nav-btn text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg">Trésorerie</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <input type="date" id="dateSelector" class="px-3 py-2 border rounded-lg" value="">
                    <button onclick="refreshData()" class="p-2 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-refresh"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Alertes -->
    <div id="alertesContainer" class="max-w-7xl mx-auto px-4 mt-4"></div>

    <div class="max-w-7xl mx-auto px-4 py-6">
        
        <!-- KPIs Principaux -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">CA Aujourd'hui</p>
                        <p class="text-2xl font-bold text-gray-900" id="caJour">0 FCFA</p>
                        <p class="text-sm text-green-600" id="evolutionCA">+0%</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-coins text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Commandes</p>
                        <p class="text-2xl font-bold text-gray-900" id="nbCommandes">0</p>
                        <p class="text-sm text-blue-600" id="panierMoyen">Panier: 0 FCFA</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-shopping-cart text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Caisse</p>
                        <p class="text-2xl font-bold text-gray-900" id="statusCaisse">Fermée</p>
                        <p class="text-sm text-orange-600" id="ecartCaisse">Écart: 0 FCFA</p>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-full">
                        <i class="fas fa-cash-register text-orange-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Objectif</p>
                        <p class="text-2xl font-bold text-gray-900" id="objectifJour">0%</p>
                        <p class="text-sm text-purple-600" id="manqueObjectif">Reste: 0 FCFA</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-target text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Évolution des ventes (7 jours)</h3>
                <canvas id="chartVentes" width="400" height="200"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Top 5 Plats du jour</h3>
                <canvas id="chartTopPlats" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Top Plats et Alertes -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Top Plats Rentables</h3>
                </div>
                <div class="p-6">
                    <div id="topPlatsRentables" class="space-y-4">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Suggestions d'Optimisation</h3>
                </div>
                <div class="p-6">
                    <div id="suggestionsOptimisation" class="space-y-4">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let dashboardData = {};
        let chartsInstances = {};

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Définir la date d'aujourd'hui
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateSelector').value = today;
            
            loadDashboardData();
            
            // Event listeners
            document.getElementById('dateSelector').addEventListener('change', function() {
                loadDashboardData();
            });
        });

        // Chargement des données du dashboard
        async function loadDashboardData() {
            try {
                const date = document.getElementById('dateSelector').value;
                console.log('Chargement des données pour:', date);
                
                // Construire l'URL relative correctement
                const baseUrl = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/admin/'));
                const apiUrl = `${baseUrl}/api/finance.php?action=dashboard&date=${date}`;
                
                console.log('URL API:', apiUrl);
                
                const response = await fetch(apiUrl);
                const responseText = await response.text();
                
                console.log('Réponse brute:', responseText);
                
                try {
                    dashboardData = JSON.parse(responseText);
                } catch (e) {
                    console.error('Erreur parsing JSON:', e);
                    console.error('Réponse reçue:', responseText);
                    throw new Error('Réponse invalide du serveur');
                }

                if (dashboardData.error) {
                    throw new Error(dashboardData.error);
                }

                updateDashboardKPIs();
                updateDashboardCharts();
                updateTopPlats();
                updateSuggestions();
                updateAlertes();

            } catch (error) {
                console.error('Erreur chargement dashboard:', error);
                showNotification('Erreur lors du chargement des données: ' + error.message, 'error');
                
                // Utiliser des données de démonstration en cas d'erreur
                useDemoData();
            }
        }

        // Données de démonstration
        function useDemoData() {
            dashboardData = {
                ventes_jour: {
                    nb_commandes: 45,
                    ca_total: 450000,
                    panier_moyen: 10000,
                    evolution: 12.5
                },
                caisse_quotidienne: {
                    statut: 'ouverte',
                    montant_ouverture: 100000,
                    ecart: 2500
                },
                objectifs: {
                    ca_objectif: 600000,
                    nb_commandes_objectif: 60
                },
                evolution_7j: [
                    {date: '2025-01-03', ca_total: 380000},
                    {date: '2025-01-04', ca_total: 420000},
                    {date: '2025-01-05', ca_total: 550000},
                    {date: '2025-01-06', ca_total: 480000},
                    {date: '2025-01-07', ca_total: 390000},
                    {date: '2025-01-08', ca_total: 440000},
                    {date: '2025-01-09', ca_total: 450000}
                ],
                top_plats: [
                    {nom: 'Thiéboudienne', quantite: 35},
                    {nom: 'Yassa Poulet', quantite: 28},
                    {nom: 'Mafé', quantite: 22},
                    {nom: 'Bassi Salté', quantite: 18},
                    {nom: 'Domoda', quantite: 15}
                ],
                top_plats_rentables: [
                    {nom: 'Sauce Feuilles', benefice_total: 37500, marge_pourcentage: 60},
                    {nom: 'Thiéboudienne', benefice_total: 60000, marge_pourcentage: 50},
                    {nom: 'Domoda', benefice_total: 30000, marge_pourcentage: 50}
                ],
                suggestions: [
                    {titre: 'Augmenter les ventes', message: 'Proposez des menus complets'},
                    {titre: 'Stock à vérifier', message: 'Vérifier le stock de poulet'}
                ],
                alertes: [
                    {id: 1, titre: 'Bonne performance', message: 'CA en hausse', priorite: 'low'}
                ]
            };
            
            updateDashboardKPIs();
            updateDashboardCharts();
            updateTopPlats();
            updateSuggestions();
            updateAlertes();
        }

        // Mise à jour des KPIs
        function updateDashboardKPIs() {
            const data = dashboardData;
            
            // CA du jour
            document.getElementById('caJour').textContent = formatMontant(data.ventes_jour?.ca_total || 0);
            
            // Evolution
            const evolution = data.ventes_jour?.evolution || 0;
            const evolutionEl = document.getElementById('evolutionCA');
            evolutionEl.textContent = evolution > 0 ? `+${evolution}%` : `${evolution}%`;
            evolutionEl.className = evolution > 0 ? 'text-sm text-green-600' : 'text-sm text-red-600';
            
            // Nombre de commandes
            document.getElementById('nbCommandes').textContent = data.ventes_jour?.nb_commandes || 0;
            document.getElementById('panierMoyen').textContent = 'Panier: ' + formatMontant(data.ventes_jour?.panier_moyen || 0);
            
            // Statut caisse
            const statutCaisse = data.caisse_quotidienne?.statut || 'fermee';
            document.getElementById('statusCaisse').textContent = statutCaisse === 'ouverte' ? 'Ouverte' : 'Fermée';
            document.getElementById('ecartCaisse').textContent = 'Écart: ' + formatMontant(data.caisse_quotidienne?.ecart || 0);
            
            // Objectif
            const objectifAtteint = ((data.ventes_jour?.ca_total || 0) / (data.objectifs?.ca_objectif || 1)) * 100;
            document.getElementById('objectifJour').textContent = Math.round(objectifAtteint) + '%';
            const manque = Math.max(0, (data.objectifs?.ca_objectif || 0) - (data.ventes_jour?.ca_total || 0));
            document.getElementById('manqueObjectif').textContent = 'Reste: ' + formatMontant(manque);
        }

        // Création des graphiques
        function updateDashboardCharts() {
            createSalesChart();
            createTopPlatsChart();
        }

        function createSalesChart() {
            const ctx = document.getElementById('chartVentes').getContext('2d');
            
            if (chartsInstances.ventes) {
                chartsInstances.ventes.destroy();
            }

            const data = dashboardData.evolution_7j || [];
            
            chartsInstances.ventes = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.date)),
                    datasets: [{
                        label: 'CA Quotidien (FCFA)',
                        data: data.map(d => d.ca_total),
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
                                    return formatMontantCourt(value);
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

        function createTopPlatsChart() {
            const ctx = document.getElementById('chartTopPlats').getContext('2d');
            
            if (chartsInstances.topPlats) {
                chartsInstances.topPlats.destroy();
            }

            const data = dashboardData.top_plats || [];
            
            chartsInstances.topPlats = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.nom),
                    datasets: [{
                        data: data.map(d => d.quantite),
                        backgroundColor: ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' portions';
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateTopPlats() {
            const container = document.getElementById('topPlatsRentables');
            const topPlats = dashboardData.top_plats_rentables || [];
            
            if (topPlats.length === 0) {
                container.innerHTML = '<p class="text-gray-500">Aucune donnée disponible</p>';
                return;
            }
            
            container.innerHTML = topPlats.map(plat => `
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div>
                        <div class="font-medium">${plat.nom}</div>
                        <div class="text-sm text-gray-600">Marge: ${plat.marge_pourcentage}%</div>
                    </div>
                    <div class="text-green-600 font-semibold">${formatMontant(plat.benefice_total)}</div>
                </div>
            `).join('');
        }

        function updateSuggestions() {
            const container = document.getElementById('suggestionsOptimisation');
            const suggestions = dashboardData.suggestions || [];
            
            if (suggestions.length === 0) {
                container.innerHTML = '<p class="text-gray-500">Aucune suggestion pour le moment</p>';
                return;
            }
            
            container.innerHTML = suggestions.map(suggestion => `
                <div class="p-3 border-l-4 border-blue-500 bg-blue-50 rounded">
                    <div class="font-medium text-blue-800">${suggestion.titre}</div>
                    <div class="text-sm text-blue-600">${suggestion.message}</div>
                </div>
            `).join('');
        }

        function updateAlertes() {
            const container = document.getElementById('alertesContainer');
            const alertes = dashboardData.alertes || [];
            
            if (alertes.length === 0) {
                container.innerHTML = '';
                return;
            }
            
            container.innerHTML = alertes.map(alerte => `
                <div class="mb-2 p-4 rounded-lg ${getAlerteClass(alerte.priorite)}">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="font-medium">${alerte.titre}</div>
                            <div class="text-sm">${alerte.message}</div>
                        </div>
                        <button onclick="dismissAlert(${alerte.id})" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function getAlerteClass(priorite) {
            switch(priorite) {
                case 'critical': return 'bg-red-100 border border-red-200 text-red-800';
                case 'high': return 'bg-orange-100 border border-orange-200 text-orange-800';
                case 'warning': return 'bg-yellow-100 border border-yellow-200 text-yellow-800';
                case 'medium': return 'bg-blue-100 border border-blue-200 text-blue-800';
                default: return 'bg-green-100 border border-green-200 text-green-800';
            }
        }

        // Fonctions utilitaires
        function formatMontant(montant) {
            // Formatage en FCFA avec espaces comme séparateurs de milliers
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(montant || 0) + ' FCFA';
        }
        
        function formatMontantCourt(montant) {
            // Format court pour les graphiques
            if (montant >= 1000000) {
                return (montant / 1000000).toFixed(1) + 'M';
            } else if (montant >= 1000) {
                return (montant / 1000).toFixed(0) + 'K';
            }
            return montant.toString();
        }

        function formatDate(dateStr) {
            const options = { day: '2-digit', month: 'short' };
            return new Date(dateStr).toLocaleDateString('fr-FR', options);
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'error' ? 'bg-red-600 text-white' :
                type === 'success' ? 'bg-green-600 text-white' :
                'bg-blue-600 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        }

        function refreshData() {
            showNotification('Actualisation des données...', 'info');
            loadDashboardData();
        }

        function dismissAlert(alerteId) {
            // Masquer l'alerte visuellement
            const alertes = dashboardData.alertes || [];
            dashboardData.alertes = alertes.filter(a => a.id !== alerteId);
            updateAlertes();
            
            // Envoyer la mise à jour au serveur
            fetch('../../api/finance.php?action=alertes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alerte_id: alerteId, statut: 'lue' })
            }).catch(err => console.error('Erreur mise à jour alerte:', err));
        }
    </script>
</body>
</html>