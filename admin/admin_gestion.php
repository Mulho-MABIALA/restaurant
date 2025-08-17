<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

// Récupération des données avec dernière connexion et statistiques
$stmt = $conn->query("
    SELECT 
        a.id,
        a.username,
        a.email,
        a.role,
        a.status,
        a.last_login,
        a.created_at,
        'admin_table' as source,
        NULL as employee_id,
        COALESCE(a.last_login, 'Jamais') as last_login_display,
        CASE WHEN a.status = 1 THEN 'Actif' ELSE 'Inactif' END as status_display
    FROM admin a
    
    UNION ALL
    
    SELECT 
        CONCAT('emp_', e.id) as id,
        CONCAT(e.prenom, '.', e.nom) as username,
        e.email,
        'admin' as role,
        CASE WHEN e.statut = 'actif' THEN 1 ELSE 0 END as status,
        NULL as last_login,
        e.date_embauche as created_at,
        'employee_table' as source,
        e.id as employee_id,
        'Jamais connecté' as last_login_display,
        CASE 
            WHEN e.statut = 'actif' THEN 'Actif (Employé)' 
            ELSE 'Inactif (Employé)' 
        END as status_display
    FROM employes e 
    WHERE e.is_admin = 1
    AND e.email NOT IN (SELECT email FROM admin WHERE email IS NOT NULL)
    
    ORDER BY created_at DESC
");
$admins = $stmt->fetchAll();

// Adapter les statistiques
$stats = [
    'total' => count($admins),
    'super_admin' => count(array_filter($admins, fn($a) => $a['role'] === 'super_admin')),
    'admin' => count(array_filter($admins, fn($a) => $a['role'] === 'admin')),
    'active' => count(array_filter($admins, fn($a) => $a['status'] == 1)),
    'inactive' => count(array_filter($admins, fn($a) => $a['status'] != 1)),
    'from_employees' => count(array_filter($admins, fn($a) => $a['source'] === 'employee_table'))
];


// Gestion des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'bulk_delete':
            $ids = json_decode($_POST['ids']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("DELETE FROM admin WHERE id IN ($placeholders) AND username != ?");
            $params = array_merge($ids, [$_SESSION['admin_username']]);
            $result = $stmt->execute($params);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'bulk_role_change':
            $ids = json_decode($_POST['ids']);
            $role = $_POST['role'];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE admin SET role = ? WHERE id IN ($placeholders)");
            $params = array_merge([$role], $ids);
            $result = $stmt->execute($params);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'toggle_status':
            $id = $_POST['id'];
            $stmt = $conn->prepare("UPDATE admin SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $result = $stmt->execute([$id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'export_csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="admins_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Username', 'Email', 'Role', 'Status', 'Last Login']);
            foreach ($admins as $admin) {
                fputcsv($output, [
                    $admin['id'],
                    $admin['username'],
                    $admin['email'],
                    $admin['role'],
                    $admin['status_display'],
                    $admin['last_login_display']
                ]);
            }
            fclose($output);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des administrateurs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#64748b'
                    }
                }
            }
        }
    </script>
    <style>
        .toast {
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        .toast.show {
            transform: translateX(0);
        }
        .dark {
            background: #0f172a;
            color: #f1f5f9;
        }
        .dark .bg-white { background: #1e293b !important; }
        .dark .text-slate-800 { color: #f1f5f9 !important; }
        .dark .text-slate-600 { color: #cbd5e1 !important; }
        .dark .border-slate-200 { border-color: #334155 !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen transition-all duration-300" id="body">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-auto">
            <!-- Header avec mode sombre -->
            <div class="bg-white border-b border-slate-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                            <i class="fas fa-users-cog text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800">Gestion des administrateurs</h1>
                            <p class="text-sm text-slate-500">Gérez les comptes administrateurs du système</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <button onclick="toggleDarkMode()" class="p-2 rounded-lg hover:bg-slate-100 transition-colors">
                            <i class="fas fa-moon text-slate-600" id="darkModeIcon"></i>
                        </button>
                        <nav class="flex items-center space-x-2 text-sm text-slate-500">
                            <a href="dashboard.php" class="hover:text-primary transition-colors">Dashboard</a>
                            <i class="fas fa-chevron-right text-xs"></i>
                            <span class="text-slate-800">Administrateurs</span>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Statistiques -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg border border-blue-200 p-6 text-white transform hover:scale-105 transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-sm font-medium">Total Administrateurs</p>
                                    <p class="text-3xl font-bold mt-1"><?= $stats['total'] ?></p>
                                    <p class="text-blue-200 text-xs mt-1">
                                        <i class="fas fa-trending-up mr-1"></i>
                                        Système actuel
                                    </p>
                                </div>
                                <div class="bg-white bg-opacity-20 rounded-full p-4">
                                    <i class="fas fa-users text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg border border-purple-200 p-6 text-white transform hover:scale-105 transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-100 text-sm font-medium">Super Administrateurs</p>
                                    <p class="text-3xl font-bold mt-1"><?= $stats['super_admin'] ?></p>
                                    <p class="text-purple-200 text-xs mt-1">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Accès complet
                                    </p>
                                </div>
                                <div class="bg-white bg-opacity-20 rounded-full p-4">
                                    <i class="fas fa-crown text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg border border-green-200 p-6 text-white transform hover:scale-105 transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-sm font-medium">Administrateurs</p>
                                    <p class="text-3xl font-bold mt-1"><?= $stats['admin'] ?></p>
                                    <p class="text-green-200 text-xs mt-1">
                                        <i class="fas fa-user-check mr-1"></i>
                                        Accès standard
                                    </p>
                                </div>
                                <div class="bg-white bg-opacity-20 rounded-full p-4">
                                    <i class="fas fa-user-tie text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-xl shadow-lg border border-emerald-200 p-6 text-white transform hover:scale-105 transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-emerald-100 text-sm font-medium">Comptes Actifs</p>
                                    <p class="text-3xl font-bold mt-1"><?= $stats['active'] ?></p>
                                    <p class="text-emerald-200 text-xs mt-1">
                                        <i class="fas fa-pulse mr-1"></i>
                                        En ligne récemment
                                    </p>
                                </div>
                                <div class="bg-white bg-opacity-20 rounded-full p-4">
                                    <i class="fas fa-check-circle text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg border border-red-200 p-6 text-white transform hover:scale-105 transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-red-100 text-sm font-medium">Comptes Inactifs</p>
                                    <p class="text-3xl font-bold mt-1"><?= $stats['inactive'] ?></p>
                                    <p class="text-red-200 text-xs mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Nécessitent attention
                                    </p>
                                </div>
                                <div class="bg-white bg-opacity-20 rounded-full p-4">
                                    <i class="fas fa-times-circle text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphique et Actions rapides -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Graphique -->
                        <div class="lg:col-span-1 bg-white rounded-xl shadow-lg border border-slate-200 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-slate-800">Répartition des rôles</h3>
                                <div class="flex space-x-1">
                                    <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                </div>
                            </div>
                            <div class="w-full max-w-xs mx-auto">
                                <canvas id="roleChart" width="200" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Actions rapides -->
                        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg border border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-800 mb-4">Actions rapides</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <button onclick="openAddModal()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg hover:from-blue-100 hover:to-blue-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-plus text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-blue-700">Nouvel Admin</span>
                                </button>
                                
                                <button onclick="exportCSV()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-lg hover:from-green-100 hover:to-green-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-download text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-green-700">Export CSV</span>
                                </button>
                                
                                <button onclick="refreshStats()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-sync-alt text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-purple-700">Actualiser</span>
                                </button>
                                
                                <button onclick="showAuditLog()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg hover:from-amber-100 hover:to-amber-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-history text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-amber-700">Historique</span>
                                </button>
                                
                                <button onclick="showSecuritySettings()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-red-50 to-red-100 rounded-lg hover:from-red-100 hover:to-red-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-shield-alt text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-red-700">Sécurité</span>
                                </button>
                                
                                <button onclick="openBulkModal()" 
                                        class="flex flex-col items-center p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg hover:from-indigo-100 hover:to-indigo-200 transition-all duration-200 group">
                                    <div class="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-tasks text-white text-lg"></i>
                                    </div>
                                    <span class="text-sm font-medium text-indigo-700">Actions Lot</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Barre d'outils améliorée -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-6">
                        <div class="flex flex-col space-y-4">
                            <!-- Titre et actions principales -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-800 flex items-center">
                                        <i class="fas fa-filter mr-2 text-blue-500"></i>
                                        Filtres et recherche
                                    </h2>
                                    <p class="text-sm text-slate-500 mt-1">Trouvez rapidement l'administrateur recherché</p>
                                </div>
                                <div class="flex items-center space-x-2 mt-3 sm:mt-0">
                                    <button onclick="clearFilters()" 
                                            class="px-3 py-2 text-slate-600 hover:text-slate-800 font-medium transition-colors text-sm border border-slate-300 rounded-lg hover:bg-slate-50">
                                        <i class="fas fa-eraser mr-1"></i>
                                        Effacer
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Filtres -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="relative">
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Recherche globale</label>
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                                        <input type="text" id="searchInput" placeholder="Nom, email..."
                                               class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Filtrer par rôle</label>
                                    <select id="roleFilter" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                        <option value="">🔍 Tous les rôles</option>
                                        <option value="admin">👤 Admin</option>
                                        <option value="super_admin">👑 Super Admin</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Filtrer par statut</label>
                                    <select id="statusFilter" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                        <option value="">🔍 Tous les statuts</option>
                                        <option value="1">✅ Actif</option>
                                        <option value="0">❌ Inactif</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Trier par</label>
                                    <select id="sortSelect" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                        <option value="id_desc">🆔 ID (récent → ancien)</option>
                                        <option value="id_asc">🆔 ID (ancien → récent)</option>
                                        <option value="username_asc">👤 Nom (A → Z)</option>
                                        <option value="username_desc">👤 Nom (Z → A)</option>
                                        <option value="email_asc">📧 Email (A → Z)</option>
                                        <option value="email_desc">📧 Email (Z → A)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Résultats et actions en lot -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 border-t border-slate-200">
                                <div class="flex items-center space-x-4">
                                    <span id="resultCount" class="text-sm text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                                        Total: <?= count($admins) ?> administrateur(s)
                                    </span>
                                    <div id="selectedCount" class="hidden text-sm text-blue-600 bg-blue-100 px-3 py-1 rounded-full"></div>
                                </div>
                                
                                <!-- Actions en lot -->
                                <div id="bulkActions" class="hidden flex flex-wrap gap-2 mt-3 sm:mt-0">
                                    <select id="bulkRoleSelect" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                        <option value="">Changer le rôle...</option>
                                        <option value="admin">👤 Admin</option>
                                        <option value="super_admin">👑 Super Admin</option>
                                    </select>
                                    
                                    <button onclick="bulkChangeRole()" 
                                            class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-all text-sm font-medium">
                                        <i class="fas fa-edit mr-1"></i>
                                        Appliquer
                                    </button>
                                    
                                    <button onclick="bulkDelete()" 
                                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all text-sm font-medium">
                                        <i class="fas fa-trash-alt mr-1"></i>
                                        Supprimer
                                    </button>
                                    
                                    <button onclick="clearSelection()" 
                                            class="px-4 py-2 bg-slate-500 text-white rounded-lg hover:bg-slate-600 transition-all text-sm font-medium">
                                        <i class="fas fa-times mr-1"></i>
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table des administrateurs améliorée -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-slate-50 to-slate-100 px-6 py-4 border-b border-slate-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-800 flex items-center">
                                        <i class="fas fa-table mr-2 text-blue-500"></i>
                                        Liste des administrateurs
                                    </h3>
                                    <p class="text-sm text-slate-500 mt-1">Gestion complète des comptes administrateurs</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button onclick="toggleTableView()" id="viewToggle"
                                            class="px-3 py-2 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors text-sm">
                                        <i class="fas fa-th-large mr-1"></i>
                                        Vue grille
                                    </button>
                                    <button onclick="exportCSV()" 
                                            class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm">
                                        <i class="fas fa-download mr-1"></i>
                                        Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vue tableau (par défaut) -->
                        <div id="tableView" class="overflow-x-auto">
                            <table class="w-full" id="adminTable">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200">
                                        <th class="text-left py-4 px-6 w-12">
                                            <div class="flex items-center">
                                                <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-blue-500 focus:ring-blue-500">
                                                <span class="ml-2 text-xs text-slate-500">Tout</span>
                                            </div>
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700 text-sm cursor-pointer hover:bg-slate-100 transition-colors" onclick="sortTable('id')">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-hashtag text-slate-400 text-xs"></i>
                                                <span>ID</span>
                                                <i class="fas fa-sort text-slate-400 text-xs"></i>
                                            </div>
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700 text-sm cursor-pointer hover:bg-slate-100 transition-colors" onclick="sortTable('username')">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-user text-slate-400 text-xs"></i>
                                                <span>Administrateur</span>
                                                <i class="fas fa-sort text-slate-400 text-xs"></i>
                                            </div>
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700 text-sm cursor-pointer hover:bg-slate-100 transition-colors" onclick="sortTable('email')">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-envelope text-slate-400 text-xs"></i>
                                                <span>Contact</span>
                                                <i class="fas fa-sort text-slate-400 text-xs"></i>
                                            </div>
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700 text-sm">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-shield-alt text-slate-400 text-xs"></i>
                                                <span>Permissions</span>
                                            </div>
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700 text-sm">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-activity text-slate-400 text-xs"></i>
                                                <span>Activité</span>
                                            </div>
                                        </th>
                                        <th class="text-center py-4 px-6 font-semibold text-slate-700 text-sm">
                                            <div class="flex items-center justify-center space-x-2">
                                                <i class="fas fa-cogs text-slate-400 text-xs"></i>
                                                <span>Actions</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200" id="adminTableBody">
                                    <?php foreach ($admins as $admin): ?>
                                        <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent transition-all duration-200 admin-row group" 
                                            data-username="<?= strtolower(htmlspecialchars($admin['username'])) ?>"
                                            data-email="<?= strtolower(htmlspecialchars($admin['email'])) ?>"
                                            data-role="<?= $admin['role'] ?>"
                                            data-status="<?= $admin['status'] ?>">
                                            <td class="py-4 px-6">
                                                <input type="checkbox" class="admin-checkbox rounded border-slate-300 text-blue-500 focus:ring-blue-500" 
                                                       value="<?= $admin['id'] ?>" onchange="updateBulkActions()">
                                            </td>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-gradient-to-br from-slate-100 to-slate-200 text-slate-600 rounded-full text-sm font-medium border-2 border-slate-300">
                                                        <?= $admin['id'] ?>
                                                    </span>
                                                </div>
                                            </td>
                                           <td class="py-4 px-6">
    <div class="flex items-center space-x-4">
        <div class="relative">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center ring-2 ring-blue-100">
                <span class="text-white font-bold text-sm">
                    <?= strtoupper(substr(htmlspecialchars($admin['username']), 0, 1)) ?>
                </span>
            </div>
            <?php if ($admin['source'] === 'employee_table'): ?>
                <div class="absolute -top-1 -right-1 w-4 h-4 bg-orange-500 rounded-full border-2 border-white flex items-center justify-center" title="Compte employé">
                    <i class="fas fa-user text-white text-xs"></i>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <div class="font-semibold text-slate-800 flex items-center">
                <?= htmlspecialchars($admin['username']) ?>
                <?php if ($admin['source'] === 'employee_table'): ?>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                        <i class="fas fa-user-friends mr-1"></i>
                        Employé-Admin
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-sm text-slate-500">
                <?= $admin['source'] === 'employee_table' ? 'Employé ID: ' . $admin['employee_id'] : 'Admin ID: ' . $admin['id'] ?>
            </div>
        </div>
    </div>
</td>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center space-x-2">
                                                    <i class="fas fa-envelope text-slate-400 text-sm"></i>
                                                    <div>
                                                        <div class="text-slate-800 font-medium">
                                                            <?= htmlspecialchars($admin['email']) ?>
                                                        </div>
                                                        <div class="text-xs text-slate-500">
                                                            <i class="fas fa-shield-check mr-1"></i>
                                                            Email vérifié
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6">
                                                <div class="flex flex-col space-y-2">
                                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium 
                                                        <?= $admin['role'] === 'super_admin' ? 'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300' : 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800 border border-blue-300' ?>">
                                                        <i class="fas fa-<?= $admin['role'] === 'super_admin' ? 'crown' : 'user-tie' ?> mr-1"></i>
                                                        <?= $admin['role'] === 'super_admin' ? 'Super Admin' : 'Administrateur' ?>
                                                    </span>
                                                    <div class="text-xs text-slate-500">
                                                        <?= $admin['role'] === 'super_admin' ? 'Accès complet' : 'Accès limité' ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6">
                                                <div class="flex flex-col space-y-2">
                                                    <button onclick="toggleStatus(<?= $admin['id'] ?>)"
                                                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition-all duration-200 hover:scale-105
                                                            <?= $admin['status'] ? 'bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300 hover:from-green-200 hover:to-green-300' : 'bg-gradient-to-r from-red-100 to-red-200 text-red-800 border border-red-300 hover:from-red-200 hover:to-red-300' ?>">
                                                        <div class="w-2 h-2 <?= $admin['status'] ? 'bg-green-500' : 'bg-red-500' ?> rounded-full mr-2 animate-pulse"></div>
                                                        <?= $admin['status_display'] ?>
                                                    </button>
                                                    <div class="text-xs text-slate-500">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?= $admin['last_login_display'] ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2">
                                                        <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= htmlspecialchars($admin['role']) ?>')"
                                                               class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-amber-100 to-amber-200 text-amber-700 rounded-lg hover:from-amber-200 hover:to-amber-300 transition-all duration-200 text-sm font-medium shadow-sm hover:shadow-md transform hover:scale-105">
                                                            <i class="fas fa-edit mr-1"></i>
                                                            Modifier
                                                        </button>
                                                        
                                                        <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                                                            <button onclick="confirmDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                                   class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-100 to-red-200 text-red-700 rounded-lg hover:from-red-200 hover:to-red-300 transition-all duration-200 text-sm font-medium shadow-sm hover:shadow-md transform hover:scale-105">
                                                                <i class="fas fa-trash-alt mr-1"></i>
                                                                Supprimer
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-500 rounded-lg text-sm font-medium cursor-not-allowed">
                                                                <i class="fas fa-lock mr-1"></i>
                                                                Protégé
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Actions toujours visibles sur mobile -->
                                                    <div class="sm:hidden flex space-x-1">
                                                        <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= htmlspecialchars($admin['role']) ?>')"
                                                               class="p-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                                                            <button onclick="confirmDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                                   class="p-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Vue grille (cachée par défaut) -->
                        <div id="gridView" class="hidden p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="adminGrid">
                                <?php foreach ($admins as $admin): ?>
                                    <div class="admin-card bg-gradient-to-br from-white to-slate-50 rounded-xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                                         data-username="<?= strtolower(htmlspecialchars($admin['username'])) ?>"
                                         data-email="<?= strtolower(htmlspecialchars($admin['email'])) ?>"
                                         data-role="<?= $admin['role'] ?>"
                                         data-status="<?= $admin['status'] ?>">
                                        
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="relative">
                                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center ring-2 ring-blue-100">
                                                        <span class="text-white font-bold">
                                                            <?= strtoupper(substr(htmlspecialchars($admin['username']), 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($_SESSION['admin_username'] === $admin['username']): ?>
                                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                                                            <i class="fas fa-check text-white text-xs"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($admin['username']) ?></h4>
                                                    <p class="text-sm text-slate-500">ID: <?= $admin['id'] ?></p>
                                                </div>
                                            </div>
                                            <input type="checkbox" class="admin-checkbox rounded border-slate-300 text-blue-500 focus:ring-blue-500" 
                                                   value="<?= $admin['id'] ?>" onchange="updateBulkActions()">
                                        </div>
                                        
                                        <div class="space-y-3 mb-4">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-envelope text-slate-400 text-sm"></i>
                                                <span class="text-sm text-slate-600"><?= htmlspecialchars($admin['email']) ?></span>
                                            </div>
                                            
                                            <div class="flex items-center justify-between">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium 
                                                    <?= $admin['role'] === 'super_admin' ? 'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800' : 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800' ?>">
                                                    <i class="fas fa-<?= $admin['role'] === 'super_admin' ? 'crown' : 'user-tie' ?> mr-1"></i>
                                                    <?= $admin['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?>
                                                </span>
                                                
                                                <button onclick="toggleStatus(<?= $admin['id'] ?>)"
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium transition-all
                                                        <?= $admin['status'] ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200' ?>">
                                                    <div class="w-2 h-2 <?= $admin['status'] ? 'bg-green-500' : 'bg-red-500' ?> rounded-full mr-1"></div>
                                                    <?= $admin['status_display'] ?>
                                                </button>
                                            </div>
                                            
                                            <div class="text-xs text-slate-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                Dernière connexion: <?= $admin['last_login_display'] ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex space-x-2">
                                            <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= htmlspecialchars($admin['role']) ?>')"
                                                   class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors text-sm font-medium">
                                                <i class="fas fa-edit mr-1"></i>
                                                Modifier
                                            </button>
                                            
                                            <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                                                <button onclick="confirmDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                       class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm font-medium">
                                                    <i class="fas fa-trash-alt mr-1"></i>
                                                    Supprimer
                                                </button>
                                            <?php else: ?>
                                                <button class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-gray-100 text-gray-500 rounded-lg cursor-not-allowed text-sm font-medium">
                                                    <i class="fas fa-lock mr-1"></i>
                                                    Protégé
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                 
                                                 
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($admins)): ?>
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-users text-slate-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-slate-800 mb-2">Aucun administrateur</h3>
                                <p class="text-slate-500 mb-6">Il n'y a actuellement aucun administrateur enregistré.</p>
                                <button onclick="openAddModal()" 
                                       class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Ajouter le premier administrateur
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="mt-6 flex items-center justify-between">
                        <a href="dashboard.php" 
                           class="inline-flex items-center text-slate-600 hover:text-primary transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Retour au dashboard
                        </a>
                        
                        <div class="text-sm text-slate-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="resultCount">Total: <?= count($admins) ?> administrateur(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- Modal d'ajout -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-plus text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Ajouter un administrateur</h3>
                            <p class="text-sm text-slate-500">Créez un nouveau compte administrateur</p>
                        </div>
                    </div>
                    <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="addForm" method="POST" action="admin_ajouter.php" class="space-y-4">
                    <div>
                        <label for="addUsername" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-user mr-1"></i>
                            Nom d'utilisateur
                        </label>
                        <input type="text" id="addUsername" name="username" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Entrez le nom d'utilisateur">
                    </div>

                    <div>
                        <label for="addEmail" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope mr-1"></i>
                            Email
                        </label>
                        <input type="email" id="addEmail" name="email" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="admin@exemple.com">
                    </div>

                    <div>
                        <label for="addRole" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Rôle
                        </label>
                        <select id="addRole" name="role" required
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                            <option value="">Choisir un rôle</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="addPassword" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock mr-1"></i>
                            Mot de passe
                        </label>
                        <input type="password" id="addPassword" name="password" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Entrez un mot de passe sécurisé">
                    </div>

                    <div>
                        <label for="addPasswordConfirm" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock mr-1"></i>
                            Confirmer le mot de passe
                        </label>
                        <input type="password" id="addPasswordConfirm" name="password_confirm" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Confirmez le mot de passe">
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" onclick="closeAddModal()"
                                class="px-4 py-2 text-slate-600 hover:text-slate-800 font-medium transition-colors">
                            Annuler
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Créer l'administrateur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-edit text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Modifier l'administrateur</h3>
                            <p class="text-sm text-slate-500">Modifiez les informations de l'administrateur</p>
                        </div>
                    </div>
                    <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="editForm" method="POST" action="admin_modifier.php" class="space-y-4">
                    <input type="hidden" id="editAdminId" name="id">
                    
                    <div>
                        <label for="editUsername" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-user mr-1"></i>
                            Nom d'utilisateur
                        </label>
                        <input type="text" id="editUsername" name="username" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                    </div>

                    <div>
                        <label for="editEmail" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope mr-1"></i>
                            Email
                        </label>
                        <input type="email" id="editEmail" name="email" required
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                    </div>

                    <div>
                        <label for="editRole" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Rôle
                        </label>
                        <select id="editRole" name="role" required
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="editPassword" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock mr-1"></i>
                            Nouveau mot de passe (optionnel)
                        </label>
                        <input type="password" id="editPassword" name="password"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Laissez vide pour conserver l'actuel">
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 text-slate-600 hover:text-slate-800 font-medium transition-colors">
                            Annuler
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-primary text-white font-medium rounded-lg hover:bg-blue-600 transition-all duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">Confirmer la suppression</h3>
                        <p class="text-sm text-slate-500">Cette action est irréversible</p>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-red-800">
                        <i class="fas fa-warning mr-2"></i>
                        Êtes-vous sûr de vouloir supprimer l'administrateur <strong id="deleteAdminName"></strong> ?
                    </p>
                    <p class="text-xs text-red-600 mt-2">
                        Toutes les données associées à ce compte seront définitivement perdues.
                    </p>
                </div>
                
                <div class="flex items-center justify-end space-x-3">
                    <button onclick="closeDeleteModal()"
                            class="px-4 py-2 text-slate-600 hover:text-slate-800 font-medium transition-colors">
                        Annuler
                    </button>
                    <button onclick="executeDelete()" id="confirmDeleteBtn"
                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-all duration-200">
                        <i class="fas fa-trash mr-2"></i>
                        Supprimer définitivement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let currentSortColumn = 'id';
        let currentSortDirection = 'desc';
        let deleteAdminId = null;
        let isDarkMode = localStorage.getItem('darkMode') === 'true';

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Appliquer le mode sombre si activé
            if (isDarkMode) {
                toggleDarkMode();
            }
            
            // Initialiser le graphique
            initChart();
            
            // Animation d'entrée pour les lignes
            animateTableRows();
            
            // Événements de recherche et filtrage
            setupSearchAndFilters();
        });

        // Basculer entre vue tableau et grille
        let isGridView = false;
        
        function toggleTableView() {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            const toggleBtn = document.getElementById('viewToggle');
            
            isGridView = !isGridView;
            
            if (isGridView) {
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-table mr-1"></i>Vue tableau';
                showToast('Vue grille activée', 'info');
            } else {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-th-large mr-1"></i>Vue grille';
                showToast('Vue tableau activée', 'info');
            }
        }

        // Filtrage amélioré pour supporter les deux vues
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            // Filtrer la vue tableau
            const tableRows = document.querySelectorAll('.admin-row');
            let visibleTableCount = 0;

            tableRows.forEach(row => {
                const username = row.dataset.username;
                const email = row.dataset.email;
                const role = row.dataset.role;
                const status = row.dataset.status;

                const matchesSearch = !searchTerm || 
                    username.includes(searchTerm) || 
                    email.includes(searchTerm);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                    visibleTableCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Filtrer la vue grille
            const gridCards = document.querySelectorAll('.admin-card');
            let visibleGridCount = 0;

            gridCards.forEach(card => {
                const username = card.dataset.username;
                const email = card.dataset.email;
                const role = card.dataset.role;
                const status = card.dataset.status;

                const matchesSearch = !searchTerm || 
                    username.includes(searchTerm) || 
                    email.includes(searchTerm);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesRole && matchesStatus) {
                    card.style.display = '';
                    visibleGridCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const visibleCount = isGridView ? visibleGridCount : visibleTableCount;
            document.getElementById('resultCount').innerHTML = `
                <i class="fas fa-search mr-1"></i>
                Affichage: ${visibleCount} administrateur(s)
            `;
        }

        // Nouvelles fonctions ajoutées
        function refreshStats() {
            showToast('Actualisation des statistiques...', 'info');
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        function showAuditLog() {
            showToast('Fonctionnalité en développement', 'info');
        }

        function showSecuritySettings() {
            showToast('Paramètres de sécurité - Bientôt disponible', 'info');
        }

        function openBulkModal() {
            showToast('Interface d\'actions en lot améliorée', 'info');
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('roleFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('sortSelect').value = 'id_desc';
            filterTable();
            showToast('Filtres effacés', 'success');
        }

        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.admin-checkbox').forEach(cb => cb.checked = false);
            updateBulkActions();
            showToast('Sélection annulée', 'info');
        }

        // Tri amélioré
        document.getElementById('sortSelect').addEventListener('change', function() {
            const [column, direction] = this.value.split('_');
            currentSortColumn = column;
            currentSortDirection = direction;
            sortTable(column);
        });

        // Mode sombre
        function toggleDarkMode() {
            isDarkMode = !isDarkMode;
            localStorage.setItem('darkMode', isDarkMode);
            
            const body = document.getElementById('body');
            const icon = document.getElementById('darkModeIcon');
            
            if (isDarkMode) {
                body.classList.add('dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                body.classList.remove('dark');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }

        // Graphique des rôles
        function initChart() {
            const ctx = document.getElementById('roleChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Super Admin', 'Admin'],
                    datasets: [{
                        data: [<?= $stats['super_admin'] ?>, <?= $stats['admin'] ?>],
                        backgroundColor: ['#8b5cf6', '#3b82f6'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        // Animation des lignes du tableau
        function animateTableRows() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        }

        // Configuration recherche et filtres
        function setupSearchAndFilters() {
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            searchInput.addEventListener('input', filterTable);
            roleFilter.addEventListener('change', filterTable);
            statusFilter.addEventListener('change', filterTable);
        }

        // Filtrage du tableau
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.admin-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const username = row.dataset.username;
                const email = row.dataset.email;
                const role = row.dataset.role;
                const status = row.dataset.status;

                const matchesSearch = !searchTerm || 
                    username.includes(searchTerm) || 
                    email.includes(searchTerm);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('resultCount').textContent = `Affichage: ${visibleCount} administrateur(s)`;
        }

        // Tri du tableau
        function sortTable(column) {
            const tbody = document.getElementById('adminTableBody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            if (currentSortColumn === column) {
                currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }

            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch (column) {
                    case 'id':
                        aVal = parseInt(a.children[1].textContent.trim());
                        bVal = parseInt(b.children[1].textContent.trim());
                        break;
                    case 'username':
                        aVal = a.dataset.username;
                        bVal = b.dataset.username;
                        break;
                    case 'email':
                        aVal = a.dataset.email;
                        bVal = b.dataset.email;
                        break;
                    default:
                        return 0;
                }

                if (currentSortDirection === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });

            // Réinsérer les lignes triées
            rows.forEach(row => tbody.appendChild(row));
        }

        // Gestion des sélections multiples
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.admin-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.remove('hidden');
                bulkActions.classList.add('flex');
                selectedCount.classList.remove('hidden');
                selectedCount.textContent = `${checkboxes.length} administrateur(s) sélectionné(s)`;
            } else {
                bulkActions.classList.add('hidden');
                bulkActions.classList.remove('flex');
                selectedCount.classList.add('hidden');
            }
        }

        // Sélectionner tout
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.admin-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkActions();
        });

        // Actions en lot
        function bulkChangeRole() {
            const selectedIds = Array.from(document.querySelectorAll('.admin-checkbox:checked')).map(cb => cb.value);
            const newRole = document.getElementById('bulkRoleSelect').value;
            
            if (!newRole) {
                showToast('Veuillez sélectionner un rôle', 'warning');
                return;
            }
            
            if (selectedIds.length === 0) {
                showToast('Aucun administrateur sélectionné', 'warning');
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=bulk_role_change&ids=${JSON.stringify(selectedIds)}&role=${newRole}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Rôle modifié pour ${selectedIds.length} administrateur(s)`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Erreur lors de la modification', 'error');
                }
            });
        }

        function bulkDelete() {
            const selectedIds = Array.from(document.querySelectorAll('.admin-checkbox:checked')).map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                showToast('Aucun administrateur sélectionné', 'warning');
                return;
            }

            if (confirm(`Êtes-vous sûr de vouloir supprimer ${selectedIds.length} administrateur(s) ?`)) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=bulk_delete&ids=${JSON.stringify(selectedIds)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${selectedIds.length} administrateur(s) supprimé(s)`, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Erreur lors de la suppression', 'error');
                    }
                });
            }
        }

        // Basculer le statut
        function toggleStatus(id) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Statut modifié avec succès', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erreur lors de la modification du statut', 'error');
                }
            });
        }

        // Export CSV
        function exportCSV() {
            window.location.href = window.location.href + '?action=export_csv';
            showToast('Export CSV en cours...', 'info');
        }

        // Modals - Ajout
        function openAddModal() {
            document.getElementById('addForm').reset();
            const modal = document.getElementById('addModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }, 10);
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            const modalContent = modal.querySelector('.transform');
            
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        // Modals - Modification
        function openEditModal(id, username, email, role) {
            document.getElementById('editAdminId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editPassword').value = '';

            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }, 10);
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            const modalContent = modal.querySelector('.transform');
            
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        // Modals - Suppression
        function confirmDelete(id, username) {
            deleteAdminId = id;
            document.getElementById('deleteAdminName').textContent = username;
            
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }, 10);
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            const modalContent = modal.querySelector('.transform');
            
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                deleteAdminId = null;
            }, 200);
        }

        function executeDelete() {
            if (!deleteAdminId) return;
            
            window.location.href = `admin_supprimer.php?id=${deleteAdminId}`;
        }

        // Fermeture des modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = ['addModal', 'editModal', 'deleteModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (!modal.classList.contains('hidden')) {
                        if (modalId === 'addModal') closeAddModal();
                        else if (modalId === 'editModal') closeEditModal();
                        else if (modalId === 'deleteModal') closeDeleteModal();
                    }
                });
            }
        });

        ['addModal', 'editModal', 'deleteModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(e) {
                if (e.target === this) {
                    if (modalId === 'addModal') closeAddModal();
                    else if (modalId === 'editModal') closeEditModal();
                    else if (modalId === 'deleteModal') closeDeleteModal();
                }
            });
        });

        // Validation du formulaire d'ajout
        document.getElementById('addForm').addEventListener('submit', function(e) {
            const password = document.getElementById('addPassword').value;
            const passwordConfirm = document.getElementById('addPasswordConfirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                showToast('Les mots de passe ne correspondent pas !', 'error');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showToast('Le mot de passe doit contenir au moins 6 caractères !', 'error');
                return false;
            }
        });

        // Système de notifications toast
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            toast.className = `toast ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 max-w-sm`;
            toast.innerHTML = `
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Animation d'entrée
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Suppression automatique après 5 secondes
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>