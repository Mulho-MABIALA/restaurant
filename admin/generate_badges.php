<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération de Badges - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        @media print {
            body { font-size: 12pt; }
            .no-print { display: none !important; }
            .badge { break-inside: avoid; }
        }
        
        .badge-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .badge-gradient-green {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        
        .badge-gradient-orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .badge-gradient-blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .badge-shadow {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1), 0 6px 6px rgba(0,0,0,0.1);
        }
        
        .qr-code {
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.1));
        }
        
        .badge-pattern {
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(255,255,255,0.2) 2%, transparent 50%),
                radial-gradient(circle at 75px 75px, rgba(255,255,255,0.1) 2%, transparent 50%);
            background-size: 100px 100px;
        }
        
        .text-shadow {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-id-badge text-purple-600 text-2xl mr-3"></i>
                    <h1 class="text-2xl font-bold text-gray-900">Génération de Badges</h1>
                </div>
                <div class="flex space-x-3">
                    <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <button onclick="generateAllBadges()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-users mr-2"></i>Tous les badges
                    </button>
                    <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-times mr-2"></i>Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="max-w-7xl mx-auto px-4 py-6 no-print">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Style de badge</label>
                    <select id="badgeStyle" onchange="updateBadgeStyle()" class="w-full px-3 py-2 border rounded-lg">
                        <option value="modern">Moderne</option>
                        <option value="classic">Classique</option>
                        <option value="minimalist">Minimaliste</option>
                        <option value="corporate">Corporate</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filtrer par poste</label>
                    <select id="filterPoste" onchange="filterEmployees()" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Tous les postes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select id="filterStatut" onchange="filterEmployees()" class="w-full px-3 py-2 border rounded-lg">
                        <option value="actif">Actifs seulement</option>
                        <option value="">Tous les statuts</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Actions</label>
                    <button onclick="refreshBadges()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg">
                        <i class="fas fa-sync mr-2"></i>Actualiser
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges Container -->
    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div id="badgesContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Les badges seront générés ici -->
        </div>
    </div>

    <script>
        let employees = [];
        let postes = [];
        let currentStyle = 'modern';
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadPostes();
            loadEmployees();
            
            // Si un ID spécifique est passé en paramètre
            const urlParams = new URLSearchParams(window.location.search);
            const employeeId = urlParams.get('id');
            if (employeeId) {
                loadSingleEmployee(employeeId);
            }
        });

        function loadPostes() {
            fetch('ajax/get_postes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        postes = data.postes;
                        updatePostesFilter();
                    }
                });
        }

        function updatePostesFilter() {
            const filterPoste = document.getElementById('filterPoste');
            filterPoste.innerHTML = '<option value="">Tous les postes</option>';
            
            postes.forEach(poste => {
                filterPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
            });
        }

        function loadEmployees() {
            fetch('ajax/get_employees.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        employees = data.employees.filter(emp => emp.statut === 'actif');
                        generateBadges(employees);
                    }
                });
        }

        function loadSingleEmployee(id) {
            fetch(`ajax/get_employee.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        employees = [data.employee];
                        generateBadges(employees);
                    }
                });
        }

        function filterEmployees() {
            const posteFilter = document.getElementById('filterPoste').value;
            const statutFilter = document.getElementById('filterStatut').value;
            
            let filtered = employees.filter(employee => {
                const matchesPoste = !posteFilter || employee.poste_id == posteFilter;
                const matchesStatut = !statutFilter || employee.statut === statutFilter;
                return matchesPoste && matchesStatut;
            });
            
            generateBadges(filtered);
        }

        function updateBadgeStyle() {
            currentStyle = document.getElementById('badgeStyle').value;
            generateBadges(employees);
        }

        function generateBadges(employeesList) {
            const container = document.getElementById('badgesContainer');
            container.innerHTML = '';
            
            employeesList.forEach(employee => {
                const badge = createBadge(employee);
                container.appendChild(badge);
            });
        }

        function createBadge(employee) {
            const badgeDiv = document.createElement('div');
            badgeDiv.className = 'badge fade-in';
            
            switch(currentStyle) {
                case 'modern':
                    badgeDiv.innerHTML = createModernBadge(employee);
                    break;
                case 'classic':
                    badgeDiv.innerHTML = createClassicBadge(employee);
                    break;
                case 'minimalist':
                    badgeDiv.innerHTML = createMinimalistBadge(employee);
                    break;
                case 'corporate':
                    badgeDiv.innerHTML = createCorporateBadge(employee);
                    break;
            }
            
            return badgeDiv;
        }

        function createModernBadge(employee) {
            const gradientClass = getGradientClass(employee.poste_couleur);
            return `
                <div class="bg-white rounded-2xl overflow-hidden badge-shadow transform hover:scale-105 transition-transform duration-300">
                    <!-- Header avec gradient -->
                    <div class="${gradientClass} badge-pattern px-6 py-8 relative">
                        <div class="absolute top-4 right-4">
                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                <i class="fas fa-utensils text-white text-lg"></i>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                                 class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover">
                            <div class="ml-4">
                                <h2 class="text-2xl font-bold text-white text-shadow">
                                    ${employee.prenom}
                                </h2>
                                <h3 class="text-xl font-semibold text-white text-shadow">
                                    ${employee.nom}
                                </h3>
                                ${employee.is_admin ? '<div class="flex items-center mt-1"><i class="fas fa-crown text-yellow-300 mr-1"></i><span class="text-sm text-white">Administrateur</span></div>' : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations -->
                    <div class="px-6 py-6">
                        <div class="text-center mb-4">
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                 style="background-color: ${employee.poste_couleur}20; color: ${employee.poste_couleur};">
                                <i class="fas fa-briefcase mr-2"></i>
                                ${employee.poste_nom || 'Non défini'}
                            </div>
                        </div>
                        
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-4 mr-3 text-gray-400"></i>
                                <span class="truncate">${employee.email}</span>
                            </div>
                            ${employee.telephone ? `
                                <div class="flex items-center">
                                    <i class="fas fa-phone w-4 mr-3 text-gray-400"></i>
                                    <span>${employee.telephone}</span>
                                </div>
                            ` : ''}
                            <div class="flex items-center">
                                <i class="fas fa-clock w-4 mr-3 text-gray-400"></i>
                                <span>${employee.heure_debut} - ${employee.heure_fin}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-calendar w-4 mr-3 text-gray-400"></i>
                                <span>Depuis ${formatDate(employee.date_embauche)}</span>
                            </div>
                        </div>
                        
                        <!-- QR Code -->
                        <div class="mt-6 text-center">
                            ${employee.qr_code ? `
                                <img src="qrcodes/${employee.qr_code}" 
                                     class="qr-code mx-auto w-24 h-24 border-2 border-gray-100 rounded-lg">
                                <p class="text-xs text-gray-500 mt-2">ID: ${employee.id}</p>
                            ` : '<div class="w-24 h-24 bg-gray-200 rounded-lg mx-auto flex items-center justify-center"><i class="fas fa-qrcode text-gray-400 text-2xl"></i></div>'}
                        </div>
                    </div>
                </div>
            `;
        }

        function createClassicBadge(employee) {
            return `
                <div class="bg-white border-2 border-gray-200 rounded-lg overflow-hidden badge-shadow">
                    <!-- Header classique -->
                    <div class="bg-gradient-to-r from-gray-800 to-gray-600 px-6 py-4">
                        <div class="text-center">
                            <h1 class="text-white font-bold text-lg">Restaurant Badge</h1>
                            <div class="w-16 h-0.5 bg-yellow-400 mx-auto mt-2"></div>
                        </div>
                    </div>
                    
                    <!-- Photo et info -->
                    <div class="px-6 py-6 text-center">
                        <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                             class="w-24 h-24 rounded-full border-4 border-gray-200 mx-auto mb-4 object-cover">
                        
                        <h2 class="text-xl font-bold text-gray-900 mb-1">
                            ${employee.prenom} ${employee.nom}
                        </h2>
                        
                        <div class="bg-gray-100 px-3 py-1 rounded-full inline-block mb-4">
                            <span class="text-sm font-medium text-gray-700">${employee.poste_nom || 'Employé'}</span>
                        </div>
                        
                        ${employee.is_admin ? '<div class="mb-4"><span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-crown mr-1"></i>Admin</span></div>' : ''}
                        
                        <div class="text-sm text-gray-600 space-y-2 mb-4">
                            <div class="truncate">${employee.email}</div>
                            <div>${employee.heure_debut} - ${employee.heure_fin}</div>
                        </div>
                        
                        <!-- QR Code -->
                        ${employee.qr_code ? `
                            <img src="qrcodes/${employee.qr_code}" 
                                 class="mx-auto w-20 h-20 border border-gray-300 rounded">
                        ` : '<div class="w-20 h-20 bg-gray-200 rounded mx-auto flex items-center justify-center"><i class="fas fa-qrcode text-gray-400"></i></div>'}
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-xs text-gray-500">ID: ${employee.id} • ${new Date().toLocaleDateString('fr-FR')}</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function createMinimalistBadge(employee) {
            return `
                <div class="bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm">
                    <!-- Header minimaliste -->
                    <div class="px-8 py-6 border-b border-gray-50">
                        <div class="flex items-center">
                            <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                                 class="w-16 h-16 rounded-full object-cover">
                            <div class="ml-4 flex-1">
                                <h2 class="text-lg font-semibold text-gray-900">
                                    ${employee.prenom} ${employee.nom}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">${employee.poste_nom || 'Employé'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contenu -->
                    <div class="px-8 py-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="text-xs text-gray-400 uppercase tracking-wider">Contact</div>
                                <div class="text-sm text-gray-700 truncate">${employee.email}</div>
                                ${employee.telephone ? `<div class="text-sm text-gray-700">${employee.telephone}</div>` : ''}
                            </div>
                            
                            <div class="text-center">
                                ${employee.qr_code ? `
                                    <img src="qrcodes/${employee.qr_code}" 
                                         class="w-16 h-16 mx-auto border border-gray-200 rounded">
                                ` : '<div class="w-16 h-16 bg-gray-100 rounded mx-auto flex items-center justify-center"><i class="fas fa-qrcode text-gray-400"></i></div>'}
                                <p class="text-xs text-gray-400 mt-2">#${employee.id}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-50">
                            <div class="flex justify-between items-center text-xs text-gray-400">
                                <span>${employee.heure_debut} - ${employee.heure_fin}</span>
                                <span>Restaurant</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function createCorporateBadge(employee) {
            return `
                <div class="bg-white border-2 border-blue-200 rounded-lg overflow-hidden">
                    <!-- Header corporate -->
                    <div class="bg-blue-900 text-white px-6 py-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-utensils text-xl mr-2"></i>
                                <span class="font-bold">RESTAURANT</span>
                            </div>
                            <div class="text-sm">ID: ${employee.id}</div>
                        </div>
                    </div>
                    
                    <!-- Contenu principal -->
                    <div class="px-6 py-6">
                        <div class="flex items-start">
                            <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                                 class="w-20 h-20 rounded border-2 border-blue-200 object-cover">
                            
                            <div class="ml-4 flex-1">
                                <h2 class="text-lg font-bold text-gray-900 mb-1">
                                    ${employee.nom.toUpperCase()}, ${employee.prenom}
                                </h2>
                                
                                <div class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium inline-block mb-2">
                                    ${employee.poste_nom || 'EMPLOYÉ'}
                                </div>
                                
                                ${employee.is_admin ? '<div class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium inline-block"><i class="fas fa-crown mr-1"></i>ADMIN</div>' : ''}
                                
                                <div class="mt-3 text-sm text-gray-600">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div><strong>Email:</strong></div>
                                        <div class="truncate">${employee.email}</div>
                                        <div><strong>Horaires:</strong></div>
                                        <div>${employee.heure_debut}-${employee.heure_fin}</div>
                                        <div><strong>Embauche:</strong></div>
                                        <div>${formatDate(employee.date_embauche)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- QR Code et validation -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-end">
                            <div>
                                ${employee.qr_code ? `
                                    <img src="qrcodes/${employee.qr_code}" 
                                         class="w-24 h-24 border border-gray-300">
                                ` : '<div class="w-24 h-24 bg-gray-200 flex items-center justify-center"><i class="fas fa-qrcode text-gray-400 text-xl"></i></div>'}
                            </div>
                            <div class="text-right text-xs text-gray-500">
                                <div>Badge valide</div>
                                <div>Généré le ${new Date().toLocaleDateString('fr-FR')}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="bg-gray-50 px-6 py-2 border-t">
                        <p class="text-xs text-gray-600 text-center">
                            Ce badge est la propriété du restaurant • Port obligatoire
                        </p>
                    </div>
                </div>
            `;
        }

        function getGradientClass(color) {
            if (!color) return 'badge-gradient';
            
            // Convertir couleur hex en classe appropriée
            const colorMap = {
                '#10B981': 'badge-gradient-green',
                '#F59E0B': 'badge-gradient-orange',
                '#3B82F6': 'badge-gradient-blue'
            };
            
            return colorMap[color] || 'badge-gradient';
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function refreshBadges() {
            loadEmployees();
        }

        function generateAllBadges() {
            document.getElementById('filterStatut').value = '';
            document.getElementById('filterPoste').value = '';
            loadEmployees();
        }
    </script>
</body>
</html>