<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .hover-scale { transition: transform 0.2s; }
        .hover-scale:hover { transform: scale(1.05); }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .notification { position: fixed; top: 20px; right: 20px; z-index: 1000; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-utensils text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Gestion Restaurant</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleView()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-th" id="viewIcon"></i>
                        <span id="viewText">Vue Cartes</span>
                    </button>
                    <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Ajouter Employé
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Statistiques Dashboard -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Employés Actifs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalActifs">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Présents Aujourd'hui</p>
                        <p class="text-2xl font-bold text-gray-900" id="presentsAujourdhui">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Nouveaux ce mois</p>
                        <p class="text-2xl font-bold text-gray-900" id="nouveauxCeMois">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Administrateurs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalAdmins">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et Recherche -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <input type="text" id="searchInput" placeholder="Rechercher par nom, email..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <select id="filterPoste" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les postes</option>
                    </select>
                </div>
                <div>
                    <select id="filterStatut" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="en_conge">En congé</option>
                        <option value="absent">Absent</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
                <div>
                    <button onclick="resetFilters()" class="w-full px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Vue Tableau -->
        <div id="tableView" class="bg-white rounded-lg shadow-md overflow-hidden card-shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employé</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poste</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salaire</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeesTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Les employés seront chargés ici -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vue Cartes -->
        <div id="cardView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
            <!-- Les cartes seront chargées ici -->
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="employeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un employé</h3>
                </div>
                
                <form id="employeeForm" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    
                    <!-- Photo de profil -->
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <img id="photoPreview" src="uploads/photos/default-avatar.png" 
                                 class="w-24 h-24 rounded-full border-4 border-gray-200 object-cover">
                            <label for="photo" class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 cursor-pointer hover:bg-blue-700">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="photo" name="photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="nom" name="nom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="poste" class="block text-sm font-medium text-gray-700 mb-2">Poste *</label>
                            <select id="poste" name="poste_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Sélectionner un poste</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">Salaire (€)</label>
                            <input type="number" id="salaire" name="salaire" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="dateEmbauche" class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                            <input type="date" id="dateEmbauche" name="date_embauche" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="statut" name="statut" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="actif">Actif</option>
                                <option value="en_conge">En congé</option>
                                <option value="absent">Absent</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="heureDebut" class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                            <input type="time" id="heureDebut" name="heure_debut" value="08:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="heureFin" class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                            <input type="time" id="heureFin" name="heure_fin" value="17:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="isAdmin" name="is_admin" value="1" 
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">
                                <i class="fas fa-crown text-yellow-500 mr-1"></i>
                                Administrateur
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="notification hidden"></div>

    <script>
        // Variables globales
        let currentView = localStorage.getItem('preferredView') || 'table';
        let employees = [];
        let postes = [];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadPostes();
            loadEmployees();
            loadStatistics();
            setView();
            
            // Auto-refresh des statistiques toutes les 30 secondes
            setInterval(loadStatistics, 30000);
            
            // Événements
            document.getElementById('searchInput').addEventListener('input', filterEmployees);
            document.getElementById('filterPoste').addEventListener('change', filterEmployees);
            document.getElementById('filterStatut').addEventListener('change', filterEmployees);
            document.getElementById('photo').addEventListener('change', previewPhoto);
            document.getElementById('employeeForm').addEventListener('submit', saveEmployee);
        });

        // Gestion des vues
        function toggleView() {
            currentView = currentView === 'table' ? 'cards' : 'table';
            localStorage.setItem('preferredView', currentView);
            setView();
        }

        function setView() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');
            
            if (currentView === 'table') {
                tableView.classList.remove('hidden');
                cardView.classList.add('hidden');
                viewIcon.className = 'fas fa-th';
                viewText.textContent = 'Vue Cartes';
            } else {
                tableView.classList.add('hidden');
                cardView.classList.remove('hidden');
                viewIcon.className = 'fas fa-list';
                viewText.textContent = 'Vue Tableau';
            }
        }

        // Chargement des données
        function loadStatistics() {
            fetch('get_statistics.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalActifs').textContent = data.statistics.total_actifs;
                        document.getElementById('presentsAujourdhui').textContent = data.statistics.presents_aujourd_hui;
                        document.getElementById('nouveauxCeMois').textContent = data.statistics.nouveaux_ce_mois;
                        document.getElementById('totalAdmins').textContent = data.statistics.total_admins;
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function loadPostes() {
            fetch('get_postes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        postes = data.postes;
                        updatePostesSelects();
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function updatePostesSelects() {
            const filterPoste = document.getElementById('filterPoste');
            const modalPoste = document.getElementById('poste');
            
            // Effacer les options existantes (sauf la première)
            filterPoste.innerHTML = '<option value="">Tous les postes</option>';
            modalPoste.innerHTML = '<option value="">Sélectionner un poste</option>';
            
            postes.forEach(poste => {
                filterPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
                modalPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
            });
        }

        function loadEmployees() {
            fetch('get_employees.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        employees = data.employees;
                        displayEmployees(employees);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Affichage des employés
        function displayEmployees(employeesList) {
            if (currentView === 'table') {
                displayTableView(employeesList);
            } else {
                displayCardView(employeesList);
            }
        }

        function displayTableView(employeesList) {
            const tbody = document.getElementById('employeesTableBody');
            tbody.innerHTML = '';
            
            employeesList.forEach(employee => {
                const row = createEmployeeRow(employee);
                tbody.appendChild(row);
            });
        }

        function createEmployeeRow(employee) {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 fade-in';
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                              style="background-color: ${employee.poste_couleur}20; color: ${employee.poste_couleur};">
                            ${employee.poste_nom || 'Non défini'}
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div>${employee.email}</div>
                    <div class="text-gray-500">${employee.telephone || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                        ${getStatusText(employee.statut)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${employee.salaire ? employee.salaire + ' €' : 'Non défini'}
                    <div class="text-xs text-gray-500">${employee.heure_debut} - ${employee.heure_fin}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="viewEmployee(${employee.id})" class="text-blue-600 hover:text-blue-900" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editEmployee(${employee.id})" class="text-green-600 hover:text-green-900" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="generateBadge(${employee.id})" class="text-purple-600 hover:text-purple-900" title="Badge">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button onclick="deleteEmployee(${employee.id})" class="text-red-600 hover:text-red-900" title="Désactiver">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            return row;
        }

        function displayCardView(employeesList) {
            const cardView = document.getElementById('cardView');
            cardView.innerHTML = '';
            
            employeesList.forEach(employee => {
                const card = createEmployeeCard(employee);
                cardView.appendChild(card);
            });
        }

        function createEmployeeCard(employee) {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 fade-in';
            
            card.innerHTML = `
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                             class="h-16 w-16 rounded-full object-cover border-2 border-gray-200">
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">
                                ${employee.prenom} ${employee.nom}
                                ${employee.is_admin ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
                            </h3>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: ${employee.poste_couleur}20; color: ${employee.poste_couleur};">
                                    ${employee.poste_nom || 'Non défini'}
                                </span>
                                <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                                    ${getStatusText(employee.statut)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-envelope w-4 mr-2"></i>
                            ${employee.email}
                        </div>
                        ${employee.telephone ? `
                            <div class="flex items-center">
                                <i class="fas fa-phone w-4 mr-2"></i>
                                ${employee.telephone}
                            </div>
                        ` : ''}
                        <div class="flex items-center">
                            <i class="fas fa-calendar w-4 mr-2"></i>
                            Embauché le ${formatDate(employee.date_embauche)}
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock w-4 mr-2"></i>
                            ${employee.heure_debut} - ${employee.heure_fin}
                        </div>
                        ${employee.salaire ? `
                            <div class="flex items-center">
                                <i class="fas fa-euro-sign w-4 mr-2"></i>
                                ${employee.salaire} €
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-4 flex justify-end space-x-2">
                        <button onclick="viewEmployee(${employee.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editEmployee(${employee.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="generateBadge(${employee.id})" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg" title="Badge">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button onclick="deleteEmployee(${employee.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Désactiver">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }

        // Fonctions utilitaires
        function getStatusClass(statut) {
            const classes = {
                'actif': 'bg-green-100 text-green-800',
                'en_conge': 'bg-yellow-100 text-yellow-800',
                'absent': 'bg-red-100 text-red-800',
                'inactif': 'bg-gray-100 text-gray-800'
            };
            return classes[statut] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(statut) {
            const texts = {
                'actif': 'Actif',
                'en_conge': 'En congé',
                'absent': 'Absent',
                'inactif': 'Inactif'
            };
            return texts[statut] || 'Inconnu';
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        // Filtrage et recherche
        function filterEmployees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const posteFilter = document.getElementById('filterPoste').value;
            const statutFilter = document.getElementById('filterStatut').value;
            
            const filtered = employees.filter(employee => {
                const matchesSearch = !searchTerm || 
                    employee.nom.toLowerCase().includes(searchTerm) ||
                    employee.prenom.toLowerCase().includes(searchTerm) ||
                    employee.email.toLowerCase().includes(searchTerm);
                
                const matchesPoste = !posteFilter || employee.poste_id == posteFilter;
                const matchesStatut = !statutFilter || employee.statut === statutFilter;
                
                return matchesSearch && matchesPoste && matchesStatut;
            });
            
            displayEmployees(filtered);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterPoste').value = '';
            document.getElementById('filterStatut').value = '';
            displayEmployees(employees);
        }

        // Gestion du modal
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un employé';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('photoPreview').src = 'uploads/photos/default-avatar.png';
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function editEmployee(id) {
            const employee = employees.find(e => e.id == id);
            if (!employee) return;
            
            document.getElementById('modalTitle').textContent = 'Modifier l\'employé';
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('nom').value = employee.nom;
            document.getElementById('prenom').value = employee.prenom;
            document.getElementById('email').value = employee.email;
            document.getElementById('telephone').value = employee.telephone || '';
            document.getElementById('poste').value = employee.poste_id || '';
            document.getElementById('salaire').value = employee.salaire || '';
            document.getElementById('dateEmbauche').value = employee.date_embauche;
            document.getElementById('statut').value = employee.statut;
            document.getElementById('heureDebut').value = employee.heure_debut;
            document.getElementById('heureFin').value = employee.heure_fin;
            document.getElementById('isAdmin').checked = employee.is_admin == 1;
            document.getElementById('photoPreview').src = `uploads/photos/${employee.photo || 'default-avatar.png'}`;
            
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

        // Prévisualisation de la photo
        function previewPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // Sauvegarde de l'employé
        function saveEmployee(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const url = formData.get('id') ? 'update_employee.php' : 'add_employee.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Employé sauvegardé avec succès!', 'success');
                    closeModal();
                    loadEmployees();
                    loadStatistics();
                } else {
                    showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de la sauvegarde', 'error');
            });
        }

        // Actions sur les employés
        function viewEmployee(id) {
            // Ouvrir une modal de détails ou rediriger vers une page de détails
            window.open(`employee_details.php?id=${id}`, '_blank');
        }

        function generateBadge(id) {
            window.open(`generate_badges.php?id=${id}`, '_blank');
        }

        function deleteEmployee(id) {
            if (confirm('Êtes-vous sûr de vouloir désactiver cet employé?')) {
                fetch('delete_employee.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Employé désactivé avec succès!', 'success');
                        loadEmployees();
                        loadStatistics();
                    } else {
                        showNotification(data.message || 'Erreur lors de la désactivation', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showNotification('Erreur lors de la désactivation', 'error');
                });
            }
        }

        // Notifications
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const colors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };
            
            notification.innerHTML = `
                <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>
                    ${message}
                    <button onclick="hideNotification()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            notification.classList.remove('hidden');
            
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('hidden');
        }

        // Gestion du clic en dehors du modal
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
