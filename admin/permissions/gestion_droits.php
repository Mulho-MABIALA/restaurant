<?php

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../../config.php'; 
require_once '../classes/PermissionManager.php'; 

$admin_id = isset($_SESSION['imsaid']) ? $_SESSION['imsaid'] : null;
$permManager = new PermissionManager($conn);
$current_page = 'gestion_droits';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];
        
        switch ($_POST['action']) {
            case 'update_permission':
                $target_admin_id = intval($_POST['admin_id']);
                $page_slug = $_POST['page_slug'];
                $permission_type = $_POST['permission_type'];
                $value = intval($_POST['value']);
                
                $query = "SELECT * FROM admin_permissions WHERE admin_id = ? AND page_slug = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$target_admin_id, $page_slug]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current) {
                    $query = "UPDATE admin_permissions SET {$permission_type} = ? WHERE admin_id = ? AND page_slug = ?";
                    $stmt = $conn->prepare($query);
                    $response['success'] = $stmt->execute([$value, $target_admin_id, $page_slug]);
                } else {
                    $permissions = [
                        'can_view' => $permission_type === 'can_view' ? $value : 0,
                        'can_create' => $permission_type === 'can_create' ? $value : 0,
                        'can_edit' => $permission_type === 'can_edit' ? $value : 0,
                        'can_delete' => $permission_type === 'can_delete' ? $value : 0
                    ];
                    $response['success'] = $permManager->grantPermission($target_admin_id, $page_slug, $permissions);
                }
                
                $response['message'] = $response['success'] ? 'Permission mise à jour' : 'Erreur lors de la mise à jour';
                break;
                
            case 'copy_permissions':
                $source_admin_id = intval($_POST['source_admin_id']);
                $target_admin_id = intval($_POST['target_admin_id']);
                
                $response['success'] = $permManager->copyPermissions($source_admin_id, $target_admin_id);
                $response['message'] = $response['success'] ? 'Permissions copiées avec succès' : 'Erreur lors de la copie';
                break;
                
            case 'reset_permissions':
                $target_admin_id = intval($_POST['admin_id']);
                
                $response['success'] = $permManager->resetPermissions($target_admin_id);
                $response['message'] = $response['success'] ? 'Permissions réinitialisées' : 'Erreur lors de la réinitialisation';
                break;
        }
        
        echo json_encode($response);
        exit;
    }
}

// Récupérer tous les admins
$query = "SELECT a.*, COALESCE(e.nom, '') as employee_name, COALESCE(e.prenom, '') as employee_firstname 
          FROM admin a 
          LEFT JOIN employes e ON a.employee_id = e.id 
          ORDER BY a.role DESC, a.username";
$stmt = $conn->query($query);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Structure des pages disponibles
$pages_structure = [
    'Dashboard' => [
        ['slug' => 'dashboard', 'name' => 'Tableau de bord']
    ],
    'Gestion Restaurant' => [
        ['slug' => 'reservations', 'name' => 'Réservations'],
        ['slug' => 'commandes', 'name' => 'Commandes'],
        ['slug' => 'gestion_plats', 'name' => 'Menus'],
        ['slug' => 'categories_plats', 'name' => 'Catégories'],
        ['slug' => 'gestion_cuisine', 'name' => 'Cuisine'],
        ['slug' => 'gallery', 'name' => 'Galerie'],
        ['slug' => 'admin_evenements', 'name' => 'Événements'],
        ['slug' => 'horaires', 'name' => 'Horaires'],
        ['slug' => 'admin_newsletter', 'name' => 'Newsletter'],
        ['slug' => 'avis_admin', 'name' => 'Avis Clients']
    ],
    'Finances' => [
        ['slug' => 'finances_dashboard', 'name' => 'Tableau de bord Finance'],
        ['slug' => 'facturation', 'name' => 'Facturation'],
        ['slug' => 'tresorerie', 'name' => 'Trésorerie'],
        ['slug' => 'rapports_finance', 'name' => 'Rapports Financiers']
    ],
    'Communication' => [
        ['slug' => 'annonces', 'name' => 'Annonces internes'],
        ['slug' => 'annonces_public', 'name' => 'Annonces publiques'],
        ['slug' => 'add_procedure', 'name' => 'Ajouter Procédures'],
        ['slug' => 'voir_annonce', 'name' => 'Voir annonces'],
        ['slug' => 'procedures', 'name' => 'Voir Procédures'],
        ['slug' => 'incidents', 'name' => 'Signalements']
    ],
    'Administration' => [
        ['slug' => 'gestion_stock', 'name' => 'Gestion des Stocks']
    ],
    'Employés' => [
        ['slug' => 'gestion_employe', 'name' => 'Liste des employés'],
        ['slug' => 'gestion_postes', 'name' => 'Gestion des postes'],
        ['slug' => 'planification_horaires', 'name' => 'Planification horaire'],
        ['slug' => 'gestion_paie', 'name' => 'Gestion paie'],
        ['slug' => 'badgeuse', 'name' => 'Badgeuse'],
        ['slug' => 'presence', 'name' => 'Présence'],
        ['slug' => 'generate_badge', 'name' => 'Générer badges']
    ],
    'Système' => [
        ['slug' => 'admin_gestion', 'name' => 'Gestion Admins'],
        ['slug' => 'gestion_droits', 'name' => 'Gestion des Droits'],
        ['slug' => 'statistiques', 'name' => 'Statistiques']
    ]
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Droits d'Accès - Restaurant Jungle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Styles critiques pour le sidebar */
        #sidebar {
            width: 320px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 40;
        }
        
        #main-content {
            margin-left: 320px !important;
            min-height: 100vh;
            transition: margin-left 0.4s ease;
            width: calc(100% - 320px);
        }
        
        #sidebar.collapsed {
            width: 85px;
        }
        
        #sidebar.collapsed ~ #main-content {
            margin-left: 85px !important;
            width: calc(100% - 85px);
        }
        
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
            }
            
            #sidebar.mobile-open {
                transform: translateX(0);
            }
            
            #main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#2563EB',
                        'primary-dark': '#1d4ed8',
                        'surface': '#1F2937',
                        'surface-light': '#374151'
                    }
                }
            }
        }
    </script>
    
    <style>
        .permission-card {
            transition: all 0.3s ease;
        }
        .permission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 min-h-screen">

<!-- Include du sidebar -->
<?php include '../includes/sidebar.php'; ?>

<!-- Contenu principal -->
<div id="main-content">
    <div class="container mx-auto px-6 py-8">
        <!-- En-tête -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-user-shield text-primary mr-3"></i>
                        Gestion des Droits d'Accès
                    </h1>
                    <p class="text-gray-600">Contrôlez les permissions des administrateurs du système</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-4">
                    <div class="text-sm text-gray-600">Total Admins</div>
                    <div class="text-3xl font-bold text-primary"><?php echo count($admins); ?></div>
                </div>
            </div>
        </div>

        <!-- Messages de notification -->
        <div id="notification" class="hidden fixed top-4 right-4 z-50 max-w-md"></div>

        <!-- Sélection de l'admin -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-user-cog text-primary mr-3"></i>
                Sélectionner un Administrateur
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($admins as $admin): ?>
                <div class="admin-card permission-card bg-gradient-to-br from-gray-50 to-white border-2 border-gray-200 rounded-xl p-5 cursor-pointer hover:border-primary"
                     onclick="loadAdminPermissions(<?php echo $admin['id']; ?>)">
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <?php if (!empty($admin['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($admin['profile_photo']); ?>" 
                                 alt="Photo" class="w-16 h-16 rounded-full object-cover">
                            <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center text-white text-2xl font-bold">
                                <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-white
                                <?php echo $admin['active'] ? 'bg-green-500' : 'bg-gray-400'; ?>"></div>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($admin['username']); ?></h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($admin['email']); ?></p>
                            <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold
                                <?php echo $admin['role'] === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                <?php echo ucfirst($admin['role']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Panneau de gestion des permissions -->
        <div id="permissions-panel" class="hidden bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-key text-primary mr-3"></i>
                    Permissions de <span id="selected-admin-name" class="text-primary ml-2"></span>
                </h2>
                <div class="flex space-x-3">
                    <button onclick="showCopyModal()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-copy mr-2"></i>Copier depuis...
                    </button>
                    <button onclick="resetPermissions()" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-redo mr-2"></i>Réinitialiser
                    </button>
                </div>
            </div>

            <div id="permissions-content" class="space-y-6"></div>
        </div>
    </div>
</div>

<!-- Modal pour copier les permissions -->
<div id="copyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4">
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Copier les permissions depuis</h3>
        <select id="source-admin-select" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-primary focus:outline-none mb-6">
            <option value="">Sélectionner un administrateur...</option>
            <?php foreach ($admins as $admin): ?>
            <option value="<?php echo $admin['id']; ?>"><?php echo htmlspecialchars($admin['username']); ?> (<?php echo $admin['role']; ?>)</option>
            <?php endforeach; ?>
        </select>
        <div class="flex space-x-3">
            <button onclick="copyPermissions()" class="flex-1 px-4 py-3 bg-primary text-white rounded-xl hover:bg-primary-dark transition font-semibold">
                <i class="fas fa-check mr-2"></i>Copier
            </button>
            <button onclick="hideCopyModal()" class="flex-1 px-4 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 transition font-semibold">
                Annuler
            </button>
        </div>
    </div>
</div>

<script>
let currentAdminId = null;
const pagesStructure = <?php echo json_encode($pages_structure); ?>;

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    
    notification.innerHTML = `
        <div class="${bgColor} text-white px-6 py-4 rounded-xl shadow-2xl flex items-center space-x-3 animate-fade-in">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} text-2xl"></i>
            <span class="font-semibold">${message}</span>
        </div>
    `;
    notification.classList.remove('hidden');
    
    setTimeout(() => {
        notification.classList.add('hidden');
    }, 3000);
}

function loadAdminPermissions(adminId) {
    currentAdminId = adminId;
    
    fetch(`get_permissions.php?admin_id=${adminId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('selected-admin-name').textContent = data.admin_name;
            document.getElementById('permissions-panel').classList.remove('hidden');
            
            renderPermissions(data.permissions);
            
            document.getElementById('permissions-panel').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur lors du chargement des permissions', 'error');
        });
}

function renderPermissions(permissions) {
    const container = document.getElementById('permissions-content');
    let html = '';
    
    for (const [category, pages] of Object.entries(pagesStructure)) {
        html += `
            <div class="permission-card bg-gradient-to-br from-gray-50 to-white border-2 border-gray-200 rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <div class="w-2 h-2 bg-primary rounded-full mr-3"></div>
                    ${category}
                </h3>
                <div class="space-y-3">
        `;
        
        pages.forEach(page => {
            const perm = permissions[page.slug] || {
                can_view: 0,
                can_create: 0,
                can_edit: 0,
                can_delete: 0
            };
            
            html += `
                <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-primary transition">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800">${page.name}</div>
                        <div class="text-sm text-gray-500">${page.slug}</div>
                    </div>
                    <div class="flex space-x-6">
                        ${renderToggle(page.slug, 'can_view', perm.can_view, 'Voir')}
                        ${renderToggle(page.slug, 'can_create', perm.can_create, 'Créer')}
                        ${renderToggle(page.slug, 'can_edit', perm.can_edit, 'Modifier')}
                        ${renderToggle(page.slug, 'can_delete', perm.can_delete, 'Supprimer')}
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

function renderToggle(pageSlug, permType, value, label) {
    const checked = value ? 'checked' : '';
    return `
        <div class="text-center">
            <label class="toggle-switch">
                <input type="checkbox" ${checked} 
                       onchange="updatePermission('${pageSlug}', '${permType}', this.checked ? 1 : 0)">
                <span class="toggle-slider"></span>
            </label>
            <div class="text-xs text-gray-600 mt-1">${label}</div>
        </div>
    `;
}

function updatePermission(pageSlug, permType, value) {
    if (!currentAdminId) return;
    
    const formData = new FormData();
    formData.append('action', 'update_permission');
    formData.append('admin_id', currentAdminId);
    formData.append('page_slug', pageSlug);
    formData.append('permission_type', permType);
    formData.append('value', value);
    
    fetch('gestion_droits.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la mise à jour', 'error');
    });
}

function showCopyModal() {
    document.getElementById('copyModal').classList.remove('hidden');
}

function hideCopyModal() {
    document.getElementById('copyModal').classList.add('hidden');
}

function copyPermissions() {
    const sourceAdminId = document.getElementById('source-admin-select').value;
    
    if (!sourceAdminId || !currentAdminId) {
        showNotification('Veuillez sélectionner un administrateur source', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'copy_permissions');
    formData.append('source_admin_id', sourceAdminId);
    formData.append('target_admin_id', currentAdminId);
    
    fetch('gestion_droits.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            hideCopyModal();
            loadAdminPermissions(currentAdminId);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la copie', 'error');
    });
}

function resetPermissions() {
    if (!currentAdminId) return;
    
    if (!confirm('Êtes-vous sûr de vouloir réinitialiser toutes les permissions de cet administrateur ?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset_permissions');
    formData.append('admin_id', currentAdminId);
    
    fetch('gestion_droits.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadAdminPermissions(currentAdminId);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la réinitialisation', 'error');
    });
}
</script>

</body>
</html>