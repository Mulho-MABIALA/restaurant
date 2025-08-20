<?php
session_start();
require_once '../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Classe pour gérer les rôles et permissions
class RolePermissionManager {
    private $conn;

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    // Vérifier si un utilisateur a une permission spécifique
    public function hasPermission($userId, $module, $action) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as has_permission
                FROM admins a
                JOIN roles r ON a.role_id = r.id
                JOIN role_permissions rp ON r.id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE a.id = ?
                AND p.module = ?
                AND p.action = ?
                AND r.actif = 1
                AND rp.accorde = 1
            ");

            $stmt->execute([$userId, $module, $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['has_permission'] > 0;
        } catch (PDOException $e) {
            error_log("Erreur vérification permission: " . $e->getMessage());
            return false;
        }
    }

    // Vérifier si un utilisateur peut accéder à un module
    public function canAccessModule($userId, $module) {
        return $this->hasPermission($userId, $module, 'view');
    }

    // Enregistrer une action dans les logs d'audit
    public function logAction($userId, $action, $module, $resourceId = null, $details = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO logs_actions (user_id, action, module, ressource_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $action,
                $module,
                $resourceId,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Erreur enregistrement log: " . $e->getMessage());
            return false;
        }
    }

    // Récupérer tous les rôles
    public function getAllRoles() {
        try {
            $stmt = $this->conn->query("
                SELECT r.*,
                       COUNT(DISTINCT a.id) as nb_utilisateurs,
                       COUNT(DISTINCT rp.permission_id) as nb_permissions
                FROM roles r
                LEFT JOIN admins a ON r.id = a.role_id AND a.actif = 1
                LEFT JOIN role_permissions rp ON r.id = rp.role_id AND rp.accorde = 1
                GROUP BY r.id
                ORDER BY r.niveau_hierarchie DESC, r.nom ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération rôles: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer toutes les permissions groupées par module
    public function getAllPermissions() {
        try {
            $stmt = $this->conn->query("
                SELECT * FROM permissions
                ORDER BY module ASC, action ASC
            ");

            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Grouper par module
            $grouped = [];
            foreach ($permissions as $permission) {
                $module = $permission['module'];
                if (!isset($grouped[$module])) {
                    $grouped[$module] = [];
                }
                $grouped[$module][] = $permission;
            }

            return $grouped;
        } catch (PDOException $e) {
            error_log("Erreur récupération permissions: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer les permissions d'un rôle
    public function getRolePermissions($roleId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, rp.accorde
                FROM permissions p
                LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
                ORDER BY p.module ASC, p.action ASC
            ");

            $stmt->execute([$roleId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération permissions rôle: " . $e->getMessage());
            return [];
        }
    }

    // Créer un nouveau rôle
    public function createRole($data) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO roles (nom, description, couleur, niveau_hierarchie, actif)
                VALUES (?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $data['nom'],
                $data['description'],
                $data['couleur'],
                $data['niveau_hierarchie'],
                $data['actif'] ?? 1
            ]);

            $roleId = $this->conn->lastInsertId();

            // Assigner les permissions de base selon le niveau hiérarchique
            $this->assignDefaultPermissions($roleId, $data['niveau_hierarchie']);

            $this->conn->commit();

            // Log de l'action
            $this->logAction($_SESSION['admin_id'], 'Création', 'roles', $roleId, [
                'nom_role' => $data['nom'],
                'niveau' => $data['niveau_hierarchie']
            ]);

            return ['success' => true, 'id' => $roleId];

        } catch (PDOException $e) {
            $this->conn->rollback();
            error_log("Erreur création rôle: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Assigner les permissions par défaut selon le niveau hiérarchique
    private function assignDefaultPermissions($roleId, $niveauHierarchie) {
        $defaultPermissions = [];

        switch ($niveauHierarchie) {
            case 0: // Employé
                $defaultPermissions = ['dashboard-view', 'pointage-view'];
                break;
            case 1: // Chef d'équipe
                $defaultPermissions = ['dashboard-view', 'dashboard-view_stats', 'reservations-view',
                                     'reservations-create', 'commandes-view', 'commandes-create',
                                     'menu-view', 'pointage-view', 'communication-view'];
                break;
            case 2: // Manager
                // Toutes les permissions sauf les critiques
                $stmt = $this->conn->query("SELECT id FROM permissions WHERE critique = 0");
                $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($permissions as $permId) {
                    $this->assignPermissionToRole($roleId, $permId);
                }
                return;
            case 3: // Administrateur
                // Toutes les permissions
                $stmt = $this->conn->query("SELECT id FROM permissions");
                $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($permissions as $permId) {
                    $this->assignPermissionToRole($roleId, $permId);
                }
                return;
        }

        // Pour les niveaux 0 et 1, assigner les permissions spécifiques
        foreach ($defaultPermissions as $permKey) {
            list($module, $action) = explode('-', $permKey);
            $stmt = $this->conn->prepare("SELECT id FROM permissions WHERE module = ? AND action = ?");
            $stmt->execute([$module, $action]);
            $permId = $stmt->fetchColumn();
            if ($permId) {
                $this->assignPermissionToRole($roleId, $permId);
            }
        }
    }

    // Assigner une permission à un rôle
    private function assignPermissionToRole($roleId, $permissionId) {
        try {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id, accorde)
                VALUES (?, ?, 1)
            ");
            return $stmt->execute([$roleId, $permissionId]);
        } catch (PDOException $e) {
            error_log("Erreur assignation permission: " . $e->getMessage());
            return false;
        }
    }

    // Modifier un rôle
    public function updateRole($roleId, $data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE roles
                SET nom = ?, description = ?, couleur = ?, niveau_hierarchie = ?, actif = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $data['nom'],
                $data['description'],
                $data['couleur'],
                $data['niveau_hierarchie'],
                $data['actif'] ?? 1,
                $roleId
            ]);

            // Log de l'action
            $this->logAction($_SESSION['admin_id'], 'Modification', 'roles', $roleId, [
                'nom_role' => $data['nom'],
                'modifications' => $data
            ]);

            return ['success' => true];

        } catch (PDOException $e) {
            error_log("Erreur modification rôle: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Supprimer un rôle
    public function deleteRole($roleId) {
        try {
            // Vérifier si le rôle est utilisé
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM admins WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $userCount = $stmt->fetchColumn();

            if ($userCount > 0) {
                return ['success' => false, 'error' => 'Ce rôle est encore utilisé par ' . $userCount . ' utilisateur(s)'];
            }

            // Supprimer les permissions associées
            $stmt = $this->conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // Supprimer le rôle
            $stmt = $this->conn->prepare("DELETE FROM roles WHERE id = ?");
            $result = $stmt->execute([$roleId]);

            // Log de l'action
            $this->logAction($_SESSION['admin_id'], 'Suppression', 'roles', $roleId, null);

            return ['success' => true];

        } catch (PDOException $e) {
            error_log("Erreur suppression rôle: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Mettre à jour les permissions d'un rôle
    public function updateRolePermissions($roleId, $permissions) {
        try {
            $this->conn->beginTransaction();

            // Supprimer toutes les permissions existantes du rôle
            $stmt = $this->conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // Ajouter les nouvelles permissions
            foreach ($permissions as $permissionId) {
                $stmt = $this->conn->prepare("
                    INSERT INTO role_permissions (role_id, permission_id, accorde)
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$roleId, $permissionId]);
            }

            $this->conn->commit();

            // Log de l'action
            $this->logAction($_SESSION['admin_id'], 'Modification', 'permissions', $roleId, [
                'nombre_permissions' => count($permissions)
            ]);

            return ['success' => true];

        } catch (PDOException $e) {
            $this->conn->rollback();
            error_log("Erreur mise à jour permissions: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Récupérer les utilisateurs avec leurs rôles
    public function getUsersWithRoles() {
        try {
            $stmt = $this->conn->query("
                SELECT a.*, r.nom as role_nom, r.couleur as role_couleur,
                       DATE_FORMAT(a.last_login, '%d/%m/%Y %H:%i') as derniere_connexion_formatted
                FROM admins a
                LEFT JOIN roles r ON a.role_id = r.id
                ORDER BY a.nom ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération utilisateurs: " . $e->getMessage());
            return [];
        }
    }

    // Modifier le rôle d'un utilisateur
    public function updateUserRole($userId, $newRoleId) {
        try {
            $stmt = $this->conn->prepare("UPDATE admins SET role_id = ? WHERE id = ?");
            $result = $stmt->execute([$newRoleId, $userId]);

            // Log de l'action
            $this->logAction($_SESSION['admin_id'], 'Modification', 'utilisateurs', $userId, [
                'nouveau_role_id' => $newRoleId
            ]);

            return ['success' => true];

        } catch (PDOException $e) {
            error_log("Erreur modification rôle utilisateur: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Récupérer les logs d'audit
    public function getAuditLogs($limit = 50, $filters = []) {
        try {
            $sql = "
                SELECT l.*, a.nom as user_nom
                FROM logs_actions l
                JOIN admins a ON l.user_id = a.id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['user_id'])) {
                $sql .= " AND l.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['action'])) {
                $sql .= " AND l.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['date'])) {
                $sql .= " AND DATE(l.timestamp) = ?";
                $params[] = $filters['date'];
            }

            $sql .= " ORDER BY l.timestamp DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération logs: " . $e->getMessage());
            return [];
        }
    }

    // Vérifier si l'utilisateur actuel peut effectuer une action sur un autre utilisateur
    public function canManageUser($currentUserId, $targetUserId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r1.niveau_hierarchie as current_level, r2.niveau_hierarchie as target_level
                FROM admins a1
                JOIN roles r1 ON a1.role_id = r1.id
                CROSS JOIN admins a2
                JOIN roles r2 ON a2.role_id = r2.id
                WHERE a1.id = ? AND a2.id = ?
            ");

            $stmt->execute([$currentUserId, $targetUserId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Un utilisateur ne peut gérer que des utilisateurs de niveau inférieur ou égal
            return $result && $result['current_level'] >= $result['target_level'];

        } catch (PDOException $e) {
            error_log("Erreur vérification gestion utilisateur: " . $e->getMessage());
            return false;
        }
    }
}

// Middleware pour vérifier les permissions
function checkPermission($module, $action, $redirect = true) {
    if (!isset($_SESSION['admin_id'])) {
        if ($redirect) {
            header('Location: ../login.php');
            exit();
        }
        return false;
    }

    global $conn;
    $roleManager = new RolePermissionManager($conn);

    if (!$roleManager->hasPermission($_SESSION['admin_id'], $module, $action)) {
        if ($redirect) {
            $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour accéder à cette fonctionnalité.";
            header('Location: ../dashboard.php');
            exit();
        }
        return false;
    }

    return true;
}

// Instance globale pour utilisation dans d'autres fichiers
if (!isset($roleManager)) {
    $roleManager = new RolePermissionManager($conn);
}

// Chargement des données initiales
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['load_data'])) {
    header('Content-Type: application/json');
    $response = [
        'roles' => $roleManager->getAllRoles(),
        'permissions' => $roleManager->getAllPermissions(),
        'users' => $roleManager->getUsersWithRoles(),
    ];
    echo json_encode($response);
    exit;
}

// API Handler pour les requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $roleManager = new RolePermissionManager($conn);
    $response = ['success' => false];

    // Vérifier que l'utilisateur est connecté
    if (!isset($_SESSION['admin_id'])) {
        $response['error'] = 'Non autorisé';
        echo json_encode($response);
        exit;
    }

    switch ($_POST['action']) {
        case 'get_roles':
            if (checkPermission('admin', 'view', false)) {
                $response['success'] = true;
                $response['data'] = $roleManager->getAllRoles();
            }
            break;

        case 'get_permissions':
            if (checkPermission('admin', 'view', false)) {
                $response['success'] = true;
                $response['data'] = $roleManager->getAllPermissions();
            }
            break;

        case 'get_role_permissions':
            if (checkPermission('admin', 'view', false) && isset($_POST['role_id'])) {
                $response['success'] = true;
                $response['data'] = $roleManager->getRolePermissions($_POST['role_id']);
            }
            break;

        case 'create_role':
            if (checkPermission('admin', 'edit', false)) {
                $data = [
                    'nom' => $_POST['nom'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'couleur' => $_POST['couleur'] ?? '#10b981',
                    'niveau_hierarchie' => intval($_POST['niveau_hierarchie'] ?? 0),
                    'actif' => isset($_POST['actif']) ? 1 : 0
                ];
                $response = $roleManager->createRole($data);
            }
            break;

        case 'update_role':
            if (checkPermission('admin', 'edit', false) && isset($_POST['role_id'])) {
                $data = [
                    'nom' => $_POST['nom'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'couleur' => $_POST['couleur'] ?? '#10b981',
                    'niveau_hierarchie' => intval($_POST['niveau_hierarchie'] ?? 0),
                    'actif' => isset($_POST['actif']) ? 1 : 0
                ];
                $response = $roleManager->updateRole($_POST['role_id'], $data);
            }
            break;

        case 'delete_role':
            if (checkPermission('admin', 'edit', false) && isset($_POST['role_id'])) {
                $response = $roleManager->deleteRole($_POST['role_id']);
            }
            break;

        case 'update_role_permissions':
            if (checkPermission('admin', 'edit', false) && isset($_POST['role_id'])) {
                $permissions = json_decode($_POST['permissions'] ?? '[]', true);
                $response = $roleManager->updateRolePermissions($_POST['role_id'], $permissions);
            }
            break;

        case 'get_users':
            if (checkPermission('employes', 'view', false)) {
                $response['success'] = true;
                $response['data'] = $roleManager->getUsersWithRoles();
            }
            break;

        case 'update_user_role':
            if (checkPermission('employes', 'manage_roles', false) &&
                isset($_POST['user_id']) && isset($_POST['role_id'])) {

                // Vérifier si l'utilisateur peut gérer cet utilisateur
                if ($roleManager->canManageUser($_SESSION['admin_id'], $_POST['user_id'])) {
                    $response = $roleManager->updateUserRole($_POST['user_id'], $_POST['role_id']);
                } else {
                    $response['error'] = 'Vous ne pouvez pas gérer cet utilisateur';
                }
            }
            break;

        case 'get_audit_logs':
            if (checkPermission('admin', 'logs', false)) {
                $filters = [
                    'user_id' => $_POST['user_id'] ?? null,
                    'action' => $_POST['filter_action'] ?? null,
                    'date' => $_POST['date'] ?? null
                ];
                $response['success'] = true;
                $response['data'] = $roleManager->getAuditLogs(100, $filters);
            }
            break;
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rôles - Restaurant Jungle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .role-card {
            transition: all 0.3s ease;
            background: white;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .permission-toggle {
            transition: all 0.3s ease;
        }
        
        .permission-toggle:checked {
            background: linear-gradient(45deg, #10b981, #059669);
        }
        
        .critical-permission {
            border: 2px solid #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<div x-data="rolesManager()" class="container mx-auto px-6 py-8">
    
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-shield-alt text-emerald-500 mr-3"></i>
                Gestion des Rôles & Permissions
            </h1>
            <p class="text-gray-600">Contrôlez l'accès aux fonctionnalités de votre restaurant</p>
        </div>
        <div class="flex space-x-4">
            <button @click="openRoleModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl shadow-lg transition-all duration-300 hover:scale-105">
                <i class="fas fa-plus mr-2"></i>
                Nouveau Rôle
            </button>
            <button @click="showAuditLog = true" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl shadow-lg transition-all duration-300 hover:scale-105">
                <i class="fas fa-history mr-2"></i>
                Audit Trail
            </button>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-emerald-100 text-sm font-medium">Rôles Actifs</h3>
                    <p class="text-3xl font-bold" x-text="roles.filter(r => r.actif).length"></p>
                </div>
                <i class="fas fa-users text-emerald-200 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-blue-100 text-sm font-medium">Permissions</h3>
                    <p class="text-3xl font-bold" x-text="permissions.length"></p>
                </div>
                <i class="fas fa-key text-blue-200 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-amber-600 to-amber-700 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-amber-100 text-sm font-medium">Permissions Critiques</h3>
                    <p class="text-3xl font-bold" x-text="permissions.filter(p => p.critique).length"></p>
                </div>
                <i class="fas fa-exclamation-triangle text-amber-200 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-purple-100 text-sm font-medium">Utilisateurs</h3>
                    <p class="text-3xl font-bold">24</p>
                </div>
                <i class="fas fa-user-tie text-purple-200 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <div class="mb-8">
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8">
                <button @click="activeTab = 'roles'" 
                        :class="activeTab === 'roles' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-300">
                    <i class="fas fa-user-tag mr-2"></i>Rôles
                </button>
                <button @click="activeTab = 'permissions'" 
                        :class="activeTab === 'permissions' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-300">
                    <i class="fas fa-key mr-2"></i>Permissions
                </button>
                <button @click="activeTab = 'users'" 
                        :class="activeTab === 'users' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-300">
                    <i class="fas fa-users mr-2"></i>Utilisateurs
                </button>
            </nav>
        </div>
    </div>

    <!-- Contenu des onglets -->
    <div x-show="activeTab === 'roles'">
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <template x-for="role in roles" :key="role.id">
                <div class="role-card rounded-2xl p-6 shadow-xl" :style="`border-left: 5px solid ${role.couleur}`">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg" :style="`background: ${role.couleur}`">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900" x-text="role.nom"></h3>
                                <p class="text-sm text-gray-600" x-text="role.description"></p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button @click="editRole(role)" class="text-blue-600 hover:text-blue-800 transition-colors">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button @click="deleteRole(role.id)" class="text-red-600 hover:text-red-800 transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Niveau hiérarchique:</span>
                            <span class="text-gray-900 font-medium" x-text="getHierarchyLabel(role.niveau_hierarchie)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Permissions:</span>
                            <span class="text-emerald-600 font-medium" x-text="getPermissionCount(role.id) + ' permissions'"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Statut:</span>
                            <span :class="role.actif ? 'text-emerald-600' : 'text-red-600'" class="font-medium">
                                <i :class="role.actif ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-1"></i>
                                <span x-text="role.actif ? 'Actif' : 'Inactif'"></span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <button @click="managePermissions(role)" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-xl transition-colors duration-300">
                            <i class="fas fa-cog mr-2"></i>Gérer les permissions
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="activeTab === 'permissions'">
        <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-200">
            <div class="mb-6">
                <input type="text" x-model="permissionSearch" placeholder="Rechercher une permission..." 
                       class="w-full bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-500 focus:outline-none focus:border-emerald-500">
            </div>
            
            <div class="space-y-6">
                <template x-for="module in groupedPermissions" :key="module.name">
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between cursor-pointer" @click="toggleModule(module.name)">
                            <div class="flex items-center space-x-3">
                                <i :class="getModuleIcon(module.name)" class="text-emerald-500"></i>
                                <h3 class="text-lg font-semibold text-gray-900" x-text="module.name"></h3>
                                <span class="text-sm text-gray-600" x-text="`(${module.permissions.length} permissions)`"></span>
                            </div>
                            <i :class="expandedModules.includes(module.name) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-600"></i>
                        </div>
                        
                        <div x-show="expandedModules.includes(module.name)" class="p-6">
                            <div class="permission-grid">
                                <template x-for="permission in module.permissions" :key="permission.id">
                                    <div :class="permission.critique ? 'critical-permission' : ''" class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                        <div class="flex items-start space-x-3">
                                            <i class="fas fa-key text-emerald-500 mt-1"></i>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900" x-text="permission.nom_affichage"></h4>
                                                <p class="text-sm text-gray-600 mt-1" x-text="permission.description"></p>
                                                <div class="flex items-center space-x-2 mt-2">
                                                    <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded" x-text="permission.action"></span>
                                                    <span x-show="permission.critique" class="text-xs bg-red-600 text-white px-2 py-1 rounded">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Critique
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'users'">
        <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-200">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Gestion des Utilisateurs</h2>
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>Nouvel Utilisateur
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-4 px-6 text-gray-600 font-medium">Utilisateur</th>
                            <th class="text-left py-4 px-6 text-gray-600 font-medium">Rôle</th>
                            <th class="text-left py-4 px-6 text-gray-600 font-medium">Dernière connexion</th>
                            <th class="text-left py-4 px-6 text-gray-600 font-medium">Statut</th>
                            <th class="text-left py-4 px-6 text-gray-600 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="user in users" :key="user.id">
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold">
                                            <span x-text="user.nom.charAt(0).toUpperCase()"></span>
                                        </div>
                                        <div>
                                            <p class="text-gray-900 font-medium" x-text="user.nom"></p>
                                            <p class="text-gray-600 text-sm" x-text="user.email"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium" 
                                          :style="`background: ${getRoleColor(user.role_id)}20; color: ${getRoleColor(user.role_id)}`"
                                          x-text="getRoleName(user.role_id)"></span>
                                </td>
                                <td class="py-4 px-6 text-gray-600" x-text="user.derniere_connexion"></td>
                                <td class="py-4 px-6">
                                    <span :class="user.actif ? 'text-emerald-600' : 'text-red-600'" class="flex items-center">
                                        <i :class="user.actif ? 'fas fa-circle' : 'fas fa-circle'" class="mr-2 text-xs"></i>
                                        <span x-text="user.actif ? 'Actif' : 'Inactif'"></span>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex space-x-2">
                                        <button @click="editUser(user)" class="text-blue-600 hover:text-blue-800 transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="toggleUserStatus(user)" 
                                                :class="user.actif ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800'"
                                                class="transition-colors">
                                            <i :class="user.actif ? 'fas fa-ban' : 'fas fa-check'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de gestion des permissions -->
    <div x-show="showPermissionModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-900">
                        Permissions pour: <span x-text="selectedRole?.nom" class="text-emerald-600"></span>
                    </h2>
                    <button @click="showPermissionModal = false" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="space-y-6">
                    <template x-for="module in groupedPermissions" :key="module.name">
                        <div class="border border-gray-200 rounded-xl">
                            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <i :class="getModuleIcon(module.name)" class="text-emerald-500"></i>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="module.name"></h3>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <span class="text-sm text-gray-600" x-text="`${getModulePermissionCount(module.name)} / ${module.permissions.length}`"></span>
                                    <button @click="toggleAllModulePermissions(module.name)" 
                                            class="text-emerald-600 hover:text-emerald-800 transition-colors text-sm">
                                        Tout sélectionner
                                    </button>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <template x-for="permission in module.permissions" :key="permission.id">
                                        <div :class="permission.critique ? 'border-red-300 bg-red-50' : 'border-gray-200'" 
                                             class="border rounded-lg p-4 transition-all duration-300 hover:border-emerald-300">
                                            <div class="flex items-start space-x-3">
                                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                    <input type="checkbox" 
                                                           :checked="hasPermission(permission.id)"
                                                           @change="togglePermission(permission.id)"
                                                           class="sr-only">
                                                    <div class="w-6 h-6 bg-gray-300 rounded-lg flex items-center justify-center transition-all duration-300"
                                                         :class="hasPermission(permission.id) ? 'bg-emerald-500' : ''">
                                                        <i class="fas fa-check text-white text-sm" 
                                                           x-show="hasPermission(permission.id)"></i>
                                                    </div>
                                                </label>
                                                <div class="flex-1">
                                                    <h4 class="font-medium text-gray-900" x-text="permission.nom_affichage"></h4>
                                                    <p class="text-sm text-gray-600 mt-1" x-text="permission.description"></p>
                                                    <div class="flex items-center space-x-2 mt-2">
                                                        <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded" 
                                                              x-text="permission.action"></span>
                                                        <span x-show="permission.critique" 
                                                              class="text-xs bg-red-600 text-white px-2 py-1 rounded flex items-center">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>Critique
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-4">
                <button @click="showPermissionModal = false" 
                        class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-xl transition-colors">
                    Annuler
                </button>
                <button @click="savePermissions()" 
                        class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-colors">
                    <i class="fas fa-save mr-2"></i>Sauvegarder
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de création/édition de rôle -->
    <div x-show="showRoleModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900" x-text="editingRole ? 'Modifier le rôle' : 'Nouveau rôle'"></h2>
            </div>
            
            <div class="p-6">
                <form @submit.prevent="saveRole()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom du rôle</label>
                            <input type="text" x-model="roleForm.nom" required
                                   class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:outline-none focus:border-emerald-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea x-model="roleForm.description" rows="3"
                                      class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:outline-none focus:border-emerald-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
                            <div class="flex items-center space-x-3">
                                <input type="color" x-model="roleForm.couleur"
                                       class="w-16 h-12 rounded-lg border-2 border-gray-300">
                                <input type="text" x-model="roleForm.couleur"
                                       class="flex-1 bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niveau hiérarchique</label>
                            <select x-model="roleForm.niveau_hierarchie"
                                    class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:outline-none focus:border-emerald-500">
                                <option value="0">Employé (Niveau 0)</option>
                                <option value="1">Chef d'équipe (Niveau 1)</option>
                                <option value="2">Manager (Niveau 2)</option>
                                <option value="3">Administrateur (Niveau 3)</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="roleForm.actif" class="sr-only">
                                <div class="w-6 h-6 bg-gray-300 rounded-lg flex items-center justify-center transition-all duration-300"
                                     :class="roleForm.actif ? 'bg-emerald-500' : ''">
                                    <i class="fas fa-check text-white text-sm" x-show="roleForm.actif"></i>
                                </div>
                                <span class="ml-3 text-gray-700">Rôle actif</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-4">
                <button @click="showRoleModal = false" 
                        class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-xl transition-colors">
                    Annuler
                </button>
                <button @click="saveRole()" 
                        class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-colors">
                    <i class="fas fa-save mr-2"></i>Sauvegarder
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Audit Log -->
    <div x-show="showAuditLog" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-history text-blue-500 mr-3"></i>Journal d'audit
                    </h2>
                    <button @click="showAuditLog = false" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <select class="bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900">
                            <option>Tous les utilisateurs</option>
                            <option>Admin Principal</option>
                            <option>Manager</option>
                        </select>
                        <select class="bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900">
                            <option>Toutes les actions</option>
                            <option>Création</option>
                            <option>Modification</option>
                            <option>Suppression</option>
                        </select>
                        <input type="date" class="bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900">
                    </div>
                </div>
                
                <div class="space-y-4">
                    <template x-for="log in auditLogs" :key="log.id">
                        <div class="bg-white rounded-xl p-4 border-l-4 border border-gray-200" :class="getLogTypeColor(log.action)">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold">
                                        <span x-text="log.user.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-gray-900 font-medium" x-text="log.user"></span>
                                            <span class="text-gray-400">•</span>
                                            <span :class="getActionColor(log.action)" class="text-sm font-medium" x-text="log.action"></span>
                                            <span class="text-gray-400">•</span>
                                            <span class="text-gray-600 text-sm" x-text="log.module"></span>
                                        </div>
                                        <p class="text-gray-700 mt-1" x-text="log.details"></p>
                                        <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                            <span>IP: <span x-text="log.ip"></span></span>
                                            <span x-text="log.timestamp"></span>
                                        </div>
                                    </div>
                                </div>
                                <i :class="getLogIcon(log.action)" class="text-lg" :class="getActionColor(log.action)"></i>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function rolesManager() {
    return {
        activeTab: 'roles',
        showPermissionModal: false,
        showRoleModal: false,
        showAuditLog: false,
        editingRole: null,
        selectedRole: null,
        permissionSearch: '',
        expandedModules: ['dashboard', 'reservations'],
        selectedPermissions: [],
        roleForm: {
            nom: '',
            description: '',
            couleur: '#10b981',
            niveau_hierarchie: 0,
            actif: true
        },
        roles: [],
        permissions: [],
        users: [],
        auditLogs: [],

        // Fonction pour charger les données depuis le serveur
        loadData() {
            fetch('gestion_droit.php?load_data=1')
                .then(response => response.json())
                .then(data => {
                    this.roles = data.roles;
                    this.permissions = Object.values(data.permissions).flat();
                    this.users = data.users;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des données:', error);
                });
        },

        // Appeler loadData au chargement de la page
        init() {
            this.loadData();
        },

        get groupedPermissions() {
            const modules = [...new Set(this.permissions.map(p => p.module))];
            return modules.map(module => ({
                name: module,
                permissions: this.permissions.filter(p => p.module === module)
            }));
        },

        getHierarchyLabel(level) {
            const labels = ['Employé', 'Chef d\'équipe', 'Manager', 'Administrateur'];
            return labels[level] || 'Inconnu';
        },

        getModuleIcon(module) {
            const icons = {
                'dashboard': 'fas fa-chart-bar',
                'reservations': 'fas fa-calendar-check',
                'menu': 'fas fa-utensils',
                'finances': 'fas fa-euro-sign',
                'admin': 'fas fa-cog',
                'stock': 'fas fa-boxes',
                'employes': 'fas fa-users'
            };
            return icons[module] || 'fas fa-key';
        },

        getPermissionCount(roleId) {
            const role = this.roles.find(r => r.id == roleId);
            return role ? role.nb_permissions : 0;
        },

        getModulePermissionCount(module) {
            const rolePermissions = this.permissions.filter(p => p.module === module && this.selectedPermissions.includes(p.id));
            return rolePermissions.length;
        },

        hasPermission(permissionId) {
            return this.selectedPermissions.includes(parseInt(permissionId));
        },

        togglePermission(permissionId) {
            const index = this.selectedPermissions.indexOf(parseInt(permissionId));
            if (index > -1) {
                this.selectedPermissions.splice(index, 1);
            } else {
                this.selectedPermissions.push(parseInt(permissionId));
            }
        },

        toggleModule(moduleName) {
            const index = this.expandedModules.indexOf(moduleName);
            if (index > -1) {
                this.expandedModules.splice(index, 1);
            } else {
                this.expandedModules.push(moduleName);
            }
        },

        toggleAllModulePermissions(module) {
            const modulePermissions = this.permissions.filter(p => p.module === module);
            const allSelected = modulePermissions.every(p => this.hasPermission(p.id));

            modulePermissions.forEach(permission => {
                const index = this.selectedPermissions.indexOf(permission.id);
                if (allSelected && index > -1) {
                    this.selectedPermissions.splice(index, 1);
                } else if (!allSelected && index === -1) {
                    this.selectedPermissions.push(permission.id);
                }
            });
        },

        openRoleModal() {
            this.editingRole = null;
            this.roleForm = {
                nom: '',
                description: '',
                couleur: '#10b981',
                niveau_hierarchie: 0,
                actif: true
            };
            this.showRoleModal = true;
        },

        editRole(role) {
            this.editingRole = role;
            this.roleForm = { ...role };
            this.showRoleModal = true;
        },

        saveRole() {
            const url = this.editingRole ? 'gestion_droit.php' : 'gestion_droit.php';
            const method = this.editingRole ? 'update_role' : 'create_role';
            const data = new FormData();
            data.append('action', method);
            data.append('nom', this.roleForm.nom);
            data.append('description', this.roleForm.description);
            data.append('couleur', this.roleForm.couleur);
            data.append('niveau_hierarchie', this.roleForm.niveau_hierarchie);
            data.append('actif', this.roleForm.actif ? 1 : 0);
            if (this.editingRole) {
                data.append('role_id', this.editingRole.id);
            }

            fetch(url, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.loadData();
                    this.showRoleModal = false;
                } else {
                    alert('Erreur: ' + (data.error || 'Une erreur est survenue.'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        },

        deleteRole(roleId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')) {
                const data = new FormData();
                data.append('action', 'delete_role');
                data.append('role_id', roleId);

                fetch('gestion_droit.php', {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.loadData();
                    } else {
                        alert('Erreur: ' + (data.error || 'Une erreur est survenue.'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue.');
                });
            }
        },

        managePermissions(role) {
            fetch('gestion_droit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_role_permissions&role_id=${role.id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.selectedRole = role;
                    this.selectedPermissions = data.data
                        .filter(p => p.accorde)
                        .map(p => p.id);
                    this.showPermissionModal = true;
                } else {
                    alert('Erreur: ' + (data.error || 'Une erreur est survenue.'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        },

        savePermissions() {
            const data = new FormData();
            data.append('action', 'update_role_permissions');
            data.append('role_id', this.selectedRole.id);
            data.append('permissions', JSON.stringify(this.selectedPermissions));

            fetch('gestion_droit.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.loadData();
                    this.showPermissionModal = false;
                } else {
                    alert('Erreur: ' + (data.error || 'Une erreur est survenue.'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        },

        getRoleColor(roleId) {
            const role = this.roles.find(r => r.id == roleId);
            return role ? role.couleur : '#6B7280';
        },

        getRoleName(roleId) {
            const role = this.roles.find(r => r.id == roleId);
            return role ? role.nom : 'Inconnu';
        },

        editUser(user) {
            console.log('Éditer utilisateur:', user);
        },

        toggleUserStatus(user) {
            const newStatus = !user.actif;
            const data = new FormData();
            data.append('action', 'update_user_status');
            data.append('user_id', user.id);
            data.append('actif', newStatus ? 1 : 0);

            fetch('gestion_droit.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    user.actif = newStatus;
                } else {
                    alert('Erreur: ' + (data.error || 'Une erreur est survenue.'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        },

        getLogTypeColor(action) {
            const colors = {
                'Création': 'border-emerald-500',
                'Modification': 'border-blue-500',
                'Suppression': 'border-red-500'
            };
            return colors[action] || 'border-gray-300';
        },

        getActionColor(action) {
            const colors = {
                'Création': 'text-emerald-600',
                'Modification': 'text-blue-600',
                'Suppression': 'text-red-600'
            };
            return colors[action] || 'text-gray-600';
        },

        getLogIcon(action) {
            const icons = {
                'Création': 'fas fa-plus-circle',
                'Modification': 'fas fa-edit',
                'Suppression': 'fas fa-trash'
            };
            return icons[action] || 'fas fa-info-circle';
        }
    }
}
</script>


</body>
</html>