<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

// Fonction pour enregistrer les logs d'activité
function logActivity($conn, $admin_id, $admin_username, $action, $target = null, $details = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, target, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$admin_id, $admin_username, $action, $target, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        error_log("Erreur log d'activité: " . $e->getMessage());
    }
}

// Fonction pour obtenir le texte d'action
function getActionText($action) {
    $actions = [
        'CREATE_ADMIN' => 'a créé l\'administrateur',
        'UPDATE_ADMIN' => 'a modifié l\'administrateur',
        'DELETE_ADMIN' => 'a supprimé l\'administrateur',
        'BULK_DELETE_ADMIN' => 'a supprimé en lot',
        'BULK_ROLE_CHANGE' => 'a changé le rôle en lot pour',
        'BULK_STATUS_CHANGE' => 'a changé le statut en lot pour',
        'TOGGLE_STATUS' => 'a changé le statut de',
        'FORCE_LOGOUT' => 'a forcé la déconnexion de'
    ];
    return $actions[$action] ?? $action;
}

// Gestion des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_admin':
            try {
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                // Vérification unicité
                $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà existant']);
                    exit;
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $result = $stmt->execute([$username, $email, $hashedPassword, $role]);
                
                if ($result) {
                    logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'CREATE_ADMIN', $username, "Création d'un nouveau compte administrateur");
                }
                
                echo json_encode(['success' => $result, 'message' => $result ? 'Administrateur ajouté avec succès' : 'Erreur lors de l\'ajout']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'edit_admin':
            try {
                $id = $_POST['id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                
                // Vérification unicité (exclure l'admin actuel)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $id]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà existant']);
                    exit;
                }
                
                $params = [$username, $email, $role, $id];
                $sql = "UPDATE admin SET username = ?, email = ?, role = ?";
                
                if (!empty($_POST['password'])) {
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $sql .= ", password = ?";
                    $params = [$username, $email, $role, $hashedPassword, $id];
                }
                
                $sql .= " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'UPDATE_ADMIN', $username, "Modification du compte administrateur ID: $id");
                }
                
                echo json_encode(['success' => $result, 'message' => $result ? 'Administrateur modifié avec succès' : 'Erreur lors de la modification']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'bulk_delete':
            $ids = json_decode($_POST['ids']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Récupérer les noms des admins à supprimer
            $stmt = $conn->prepare("SELECT username FROM admin WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt = $conn->prepare("DELETE FROM admin WHERE id IN ($placeholders) AND username != ?");
            $params = array_merge($ids, [$_SESSION['admin_username']]);
            $result = $stmt->execute($params);
            
            if ($result) {
                logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'BULK_DELETE_ADMIN', implode(', ', $usernames), 'Suppression en lot de ' . count($usernames) . ' administrateur(s)');
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'bulk_role_change':
            $ids = json_decode($_POST['ids']);
            $role = $_POST['role'];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Récupérer les noms des admins concernés
            $stmt = $conn->prepare("SELECT username FROM admin WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt = $conn->prepare("UPDATE admin SET role = ? WHERE id IN ($placeholders)");
            $params = array_merge([$role], $ids);
            $result = $stmt->execute($params);
            
            if ($result) {
                logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'BULK_ROLE_CHANGE', implode(', ', $usernames), "Changement de rôle en lot vers: $role");
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'bulk_status_change':
            $ids = json_decode($_POST['ids']);
            $status = $_POST['status'];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            $stmt = $conn->prepare("SELECT username FROM admin WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt = $conn->prepare("UPDATE admin SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$status], $ids);
            $result = $stmt->execute($params);
            
            if ($result) {
                $statusText = $status ? 'activation' : 'désactivation';
                logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'BULK_STATUS_CHANGE', implode(', ', $usernames), "Changement de statut en lot: $statusText");
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'toggle_status':
            $id = $_POST['id'];
            
            // Récupérer le nom et le statut actuel
            $stmt = $conn->prepare("SELECT username, status FROM admin WHERE id = ?");
            $stmt->execute([$id]);
            $admin = $stmt->fetch();
            
            $stmt = $conn->prepare("UPDATE admin SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $admin) {
                $newStatus = $admin['status'] ? 'désactivé' : 'activé';
                logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'TOGGLE_STATUS', $admin['username'], "Compte $newStatus");
            }
            
            echo json_encode(['success' => $result]);
            exit;

        case 'force_logout':
            try {
                $adminId = $_POST['admin_id'];
                
                // Récupérer les infos de l'admin
                $stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
                $stmt->execute([$adminId]);
                $targetAdmin = $stmt->fetch();
                
                // Marquer la session comme expirée
                $stmt = $conn->prepare("UPDATE admin_sessions SET is_active = 0, logged_out_at = NOW() WHERE admin_id = ? AND is_active = 1");
                $result = $stmt->execute([$adminId]);
                
                if ($result && $targetAdmin) {
                    logActivity($conn, $_SESSION['admin_id'] ?? 0, $_SESSION['admin_username'], 'FORCE_LOGOUT', $targetAdmin['username'], 'Déconnexion forcée');
                }
                
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;

        case 'get_admin_details':
            try {
                $adminId = $_POST['admin_id'];
                
                // Informations de base de l'admin avec mot de passe
                $stmt = $conn->prepare("
                    SELECT a.*, 
                           COALESCE(DATE_FORMAT(a.last_login, '%d/%m/%Y %H:%i:%s'), 'Jamais') as last_login_display,
                           CASE WHEN a.status = 1 THEN 'Actif' ELSE 'Inactif' END as status_display,
                           LEFT(a.password, 20) as password_preview
                    FROM admin a 
                    WHERE a.id = ?
                ");
                $stmt->execute([$adminId]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$admin) {
                    echo json_encode(['success' => false, 'message' => 'Administrateur non trouvé']);
                    exit;
                }
                
                // Sessions actives - Correction de la requête
                $stmt = $conn->prepare("
                    SELECT session_id, ip_address, user_agent, logged_in_at, last_activity
                    FROM admin_sessions 
                    WHERE admin_id = ? AND is_active = 1 
                    ORDER BY last_activity DESC
                ");
                $stmt->execute([$adminId]);
                $activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Historique des connexions (10 dernières)
                $stmt = $conn->prepare("
                    SELECT session_id, ip_address, user_agent, logged_in_at, logged_out_at, is_active
                    FROM admin_sessions 
                    WHERE admin_id = ? 
                    ORDER BY logged_in_at DESC 
                    LIMIT 10
                ");
                $stmt->execute([$adminId]);
                $loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Journal d'activité (20 dernières actions)
                $stmt = $conn->prepare("
                    SELECT * FROM admin_logs 
                    WHERE admin_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 20
                ");
                $stmt->execute([$adminId]);
                $activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'admin' => $admin,
                    'active_sessions' => $activeSessions,
                    'login_history' => $loginHistory,
                    'activity_log' => $activityLog
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;

        case 'get_activity_logs':
            try {
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $whereClause = "WHERE 1=1";
                $params = [];
                
                if (!empty($_POST['admin_filter'])) {
                    $whereClause .= " AND admin_username LIKE ?";
                    $params[] = '%' . $_POST['admin_filter'] . '%';
                }
                
                if (!empty($_POST['action_filter'])) {
                    $whereClause .= " AND action = ?";
                    $params[] = $_POST['action_filter'];
                }
                
                if (!empty($_POST['date_filter'])) {
                    $whereClause .= " AND DATE(created_at) = ?";
                    $params[] = $_POST['date_filter'];
                }
                
                // Compter le total
                $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_logs $whereClause");
                $stmt->execute($params);
                $total = $stmt->fetchColumn();
                
                // Récupérer les logs
                $stmt = $conn->prepare("
                    SELECT * FROM admin_logs 
                    $whereClause 
                    ORDER BY created_at DESC 
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'logs' => $logs,
                    'total' => $total,
                    'page' => $page,
                    'total_pages' => ceil($total / $limit)
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_employee_email':
            try {
                $username = trim($_POST['username']);
                
                // Rechercher l'email de l'employé par nom, prénom, matricule ou code_numerique
                $stmt = $conn->prepare("
                    SELECT email, nom, prenom, matricule 
                    FROM employes 
                    WHERE LOWER(nom) LIKE LOWER(?) 
                       OR LOWER(prenom) LIKE LOWER(?) 
                       OR LOWER(matricule) LIKE LOWER(?)
                       OR LOWER(code_numerique) LIKE LOWER(?)
                       OR LOWER(CONCAT(nom, ' ', prenom)) LIKE LOWER(?)
                       OR LOWER(CONCAT(prenom, ' ', nom)) LIKE LOWER(?)
                    LIMIT 1
                ");
                
                $searchTerm = "%{$username}%";
                $fullName = "%{$username}%";
                
                $stmt->execute([
                    $searchTerm, // nom
                    $searchTerm, // prenom  
                    $username,   // matricule (exact)
                    $username,   // code_numerique (exact)
                    $fullName,   // nom + prenom
                    $fullName    // prenom + nom
                ]);
                
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    echo json_encode([
                        'success' => true, 
                        'email' => $employee['email'],
                        'nom' => $employee['nom'],
                        'prenom' => $employee['prenom'],
                        'matricule' => $employee['matricule']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Récupération des données avec dernière connexion et sessions actives corrigée
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
        a.employee_id,
        COALESCE(DATE_FORMAT(a.last_login, '%d/%m/%Y %H:%i'), 'Jamais') as last_login_display,
        CASE WHEN a.status = 1 THEN 'Actif' ELSE 'Inactif' END as status_display,
        CASE 
            WHEN EXISTS(
                SELECT 1 FROM admin_sessions s 
                WHERE s.admin_id = a.id 
                AND s.is_active = 1 
                AND s.last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ) THEN 1 
            ELSE 0 
        END as is_online
    FROM admin a
    ORDER BY created_at DESC
");
$admins = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($admins),
    'super_admin' => count(array_filter($admins, fn($a) => $a['role'] === 'superadmin')),
    'admin' => count(array_filter($admins, fn($a) => $a['role'] === 'admin')),
    'active' => count(array_filter($admins, fn($a) => $a['status'] == 1)),
    'inactive' => count(array_filter($admins, fn($a) => $a['status'] != 1)),
    'online' => count(array_filter($admins, fn($a) => $a['is_online'] == 1))
];

// Récupération des logs d'activité récents
$recentLogs = [];
try {
    $stmt = $conn->prepare("
        SELECT admin_username, action, target, created_at, details 
        FROM admin_logs 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table admin_logs n'existe peut-être pas encore
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
        
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .floating-icon {
            animation: float 3s ease-in-out infinite;
        }

        .modal-overlay {
            backdrop-filter: blur(4px);
            background: rgba(0, 0, 0, 0.3);
        }

        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .online-indicator {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .bulk-actions {
            transform: translateY(-100%);
            transition: transform 0.3s ease;
        }

        .bulk-actions.show {
            transform: translateY(0);
        }

        /* Styles pour les modals carrés simples */
        .modal-simple {
            background: white;
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            border-color: #9ca3af;
        }

        .password-display {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #6b7280;
            word-break: break-all;
        }
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
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
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
                            <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-blue-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-blue-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-users text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                                        <div class="text-xs text-blue-500 font-medium">TOTAL</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">Total</h3>
                            </div>
                        </div>
                        
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-purple-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-purple-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-crown text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-purple-600"><?= $stats['super_admin'] ?></div>
                                        <div class="text-xs text-purple-500 font-medium">SUPER</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">Super Admin</h3>
                            </div>
                        </div>
                        
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-green-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-green-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-green-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-user-tie text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-green-600"><?= $stats['admin'] ?></div>
                                        <div class="text-xs text-green-500 font-medium">ADMIN</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">Admin</h3>
                            </div>
                        </div>
                        
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-emerald-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-emerald-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-check-circle text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-emerald-600"><?= $stats['active'] ?></div>
                                        <div class="text-xs text-emerald-500 font-medium">ACTIFS</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">Actifs</h3>
                            </div>
                        </div>
                        
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-red-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-red-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-red-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-times-circle text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-red-600"><?= $stats['inactive'] ?></div>
                                        <div class="text-xs text-red-500 font-medium">INACTIFS</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">Inactifs</h3>
                            </div>
                        </div>
                        
                        <div class="stat-card glass-effect rounded-2xl shadow-lg p-4 relative overflow-hidden border-2 border-indigo-100">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-indigo-500 rounded-full -translate-y-10 translate-x-10 opacity-10"></div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-wifi text-white text-sm"></i>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-indigo-600"><?= $stats['online'] ?></div>
                                        <div class="text-xs text-indigo-500 font-medium">EN LIGNE</div>
                                    </div>
                                </div>
                                <h3 class="font-semibold text-slate-800 text-sm">En ligne</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Actions en lot -->
                    <div id="bulkActions" class="bulk-actions bg-blue-600 text-white rounded-xl shadow-lg p-4 mb-6 hidden">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <span id="selectedCount" class="font-semibold">0 sélectionné(s)</span>
                                <button onclick="selectAll()" class="text-blue-200 hover:text-white text-sm">
                                    <i class="fas fa-check-double mr-1"></i>Tout sélectionner
                                </button>
                                <button onclick="clearSelection()" class="text-blue-200 hover:text-white text-sm">
                                    <i class="fas fa-times mr-1"></i>Désélectionner tout
                                </button>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <select id="bulkRoleSelect" class="px-3 py-1 rounded bg-white text-slate-800 text-sm">
                                    <option value="">Changer le rôle</option>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Super Admin</option>
                                </select>
                                
                                <button onclick="bulkRoleChange()" class="px-3 py-1 bg-purple-500 hover:bg-purple-600 rounded text-sm transition-colors">
                                    <i class="fas fa-user-tag mr-1"></i>Appliquer
                                </button>
                                
                                <button onclick="bulkStatusChange(1)" class="px-3 py-1 bg-green-500 hover:bg-green-600 rounded text-sm transition-colors">
                                    <i class="fas fa-check mr-1"></i>Activer
                                </button>
                                
                                <button onclick="bulkStatusChange(0)" class="px-3 py-1 bg-yellow-500 hover:bg-yellow-600 rounded text-sm transition-colors">
                                    <i class="fas fa-pause mr-1"></i>Désactiver
                                </button>
                                
                                <button onclick="bulkDelete()" class="px-3 py-1 bg-red-500 hover:bg-red-600 rounded text-sm transition-colors">
                                    <i class="fas fa-trash mr-1"></i>Supprimer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Barre d'outils -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-6">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-800 flex items-center">
                                    <i class="fas fa-filter mr-2 text-blue-500"></i>
                                    Filtres et actions
                                </h2>
                            </div>
                            <div class="flex items-center space-x-2 mt-3 lg:mt-0">
                                <button onclick="openAddModal()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i>
                                    Nouvel Admin
                                </button>
                                <button onclick="openActivityLogModal()" 
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                    <i class="fas fa-history mr-1"></i>
                                    Journal
                                </button>
                                <button onclick="toggleTableView()" id="viewToggle"
                                        class="px-3 py-2 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors text-sm">
                                    <i class="fas fa-th-large mr-1"></i>
                                    Vue grille
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="searchInput" placeholder="Rechercher..."
                                       class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <select id="roleFilter" class="px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Tous les rôles</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Super Admin</option>
                            </select>
                            
                            <select id="statusFilter" class="px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Tous les statuts</option>
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                            
                            <select id="onlineFilter" class="px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Tous</option>
                                <option value="1">En ligne</option>
                                <option value="0">Hors ligne</option>
                            </select>
                        </div>
                    </div>

                    <!-- Activité récente -->
                    <?php if (!empty($recentLogs)): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-slate-800 flex items-center">
                                <i class="fas fa-clock mr-2 text-green-500"></i>
                                Activité récente
                            </h3>
                            <button onclick="openActivityLogModal()" class="text-blue-600 hover:text-blue-700 text-sm">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        </div>
                        <div class="space-y-3 max-h-40 overflow-y-auto">
                            <?php foreach (array_slice($recentLogs, 0, 5) as $log): ?>
                                <div class="flex items-center space-x-3 py-2">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    <div class="flex-1">
                                        <div class="text-sm text-slate-800">
                                            <span class="font-medium"><?= htmlspecialchars($log['admin_username']) ?></span>
                                            <?= getActionText($log['action']) ?>
                                            <?php if ($log['target']): ?>
                                                <span class="font-medium"><?= htmlspecialchars($log['target']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Table des administrateurs -->
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <!-- Vue tableau -->
                        <div id="tableView" class="overflow-x-auto">
                            <table class="w-full" id="adminTable">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200">
                                        <th class="text-left py-4 px-6">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" 
                                                   class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">ID</th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">Administrateur</th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">Email</th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">Rôle</th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">Statut</th>
                                        <th class="text-left py-4 px-6 font-semibold text-slate-700">Dernière connexion</th>
                                        <th class="text-center py-4 px-6 font-semibold text-slate-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200" id="adminTableBody">
                                    <?php foreach ($admins as $admin): ?>
                                        <tr class="hover:bg-slate-50 transition-colors admin-row" 
                                            data-username="<?= strtolower(htmlspecialchars($admin['username'])) ?>"
                                            data-email="<?= strtolower(htmlspecialchars($admin['email'])) ?>"
                                            data-role="<?= $admin['role'] ?>"
                                            data-status="<?= $admin['status'] ?>"
                                            data-online="<?= $admin['is_online'] ?>"
                                            data-admin-id="<?= $admin['id'] ?>">
                                            <td class="py-4 px-6">
                                                <input type="checkbox" class="admin-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500" 
                                                       value="<?= $admin['id'] ?>" onchange="updateBulkActions()">
                                            </td>
                                            <td class="py-4 px-6 text-slate-800"><?= $admin['id'] ?></td>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center space-x-3">
                                                    <div class="relative">
                                                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                                                            <span class="text-white font-medium">
                                                                <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                                                            </span>
                                                        </div>
                                                        <?php if ($admin['is_online']): ?>
                                                            <div class="absolute -top-1 -right-1 online-indicator border-2 border-white"></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-slate-800 flex items-center">
                                                            <?= htmlspecialchars($admin['username']) ?>
                                                            <?php if ($admin['is_online']): ?>
                                                                <span class="ml-2 text-xs text-green-600 font-medium">En ligne</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6 text-slate-600"><?= htmlspecialchars($admin['email']) ?></td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?= $admin['role'] === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= $admin['role'] === 'superadmin' ? 'Super Admin' : 'Admin' ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?= $admin['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $admin['status_display'] ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-slate-600"><?= $admin['last_login_display'] ?></td>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <button onclick="viewAdminDetails(<?= $admin['id'] ?>)"
                                                           class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors text-sm">
                                                        <i class="fas fa-eye mr-1"></i>Voir
                                                    </button>
                                                    
                                                    <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= $admin['role'] ?>')"
                                                           class="px-3 py-1 bg-amber-100 text-amber-700 rounded hover:bg-amber-200 transition-colors text-sm">
                                                        <i class="fas fa-edit mr-1"></i>Modifier
                                                    </button>
                                                    
                                                    <?php if ($admin['is_online'] && $_SESSION['admin_username'] !== $admin['username']): ?>
                                                        <button onclick="forceLogout(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                               class="px-3 py-1 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors text-sm">
                                                            <i class="fas fa-sign-out-alt mr-1"></i>Déconnecter
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                                                        <button onclick="confirmDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                               class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors text-sm">
                                                            <i class="fas fa-trash-alt mr-1"></i>Supprimer
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Vue grille -->
                        <div id="gridView" class="hidden p-6">
                            <div class="grid-view" id="adminGrid">
                                <?php foreach ($admins as $admin): ?>
                                    <div class="admin-card admin-row-grid relative" 
                                         data-username="<?= strtolower(htmlspecialchars($admin['username'])) ?>"
                                         data-email="<?= strtolower(htmlspecialchars($admin['email'])) ?>"
                                         data-role="<?= $admin['role'] ?>"
                                         data-status="<?= $admin['status'] ?>"
                                         data-online="<?= $admin['is_online'] ?>"
                                         data-admin-id="<?= $admin['id'] ?>">
                                        
                                        <div class="absolute top-4 left-4">
                                            <input type="checkbox" class="admin-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500" 
                                                   value="<?= $admin['id'] ?>" onchange="updateBulkActions()">
                                        </div>
                                        
                                        <div class="flex items-center space-x-4 mb-4 mt-6">
                                            <div class="relative">
                                                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-bold">
                                                        <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                                                    </span>
                                                </div>
                                                <?php if ($admin['is_online']): ?>
                                                    <div class="absolute -top-1 -right-1 online-indicator border-2 border-white"></div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-slate-800 flex items-center">
                                                    <?= htmlspecialchars($admin['username']) ?>
                                                    <?php if ($admin['is_online']): ?>
                                                        <span class="ml-2 text-xs text-green-600 font-medium">En ligne</span>
                                                    <?php endif; ?>
                                                </h3>
                                                <p class="text-sm text-slate-500">ID: <?= $admin['id'] ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-2 mb-4">
                                            <p class="text-sm text-slate-600">
                                                <i class="fas fa-envelope mr-2"></i>
                                                <?= htmlspecialchars($admin['email']) ?>
                                            </p>
                                            <div class="flex items-center justify-between">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?= $admin['role'] === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= $admin['role'] === 'superadmin' ? 'Super Admin' : 'Admin' ?>
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?= $admin['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $admin['status_display'] ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= $admin['last_login_display'] ?>
                                            </p>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-2">
                                            <button onclick="viewAdminDetails(<?= $admin['id'] ?>)"
                                                   class="px-3 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors text-sm">
                                                <i class="fas fa-eye mr-1"></i>Voir
                                            </button>
                                            
                                            <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= $admin['role'] ?>')"
                                                   class="px-3 py-2 bg-amber-100 text-amber-700 rounded hover:bg-amber-200 transition-colors text-sm">
                                                <i class="fas fa-edit mr-1"></i>Modifier
                                            </button>
                                            
                                            <?php if ($admin['is_online'] && $_SESSION['admin_username'] !== $admin['username']): ?>
                                                <button onclick="forceLogout(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                       class="px-3 py-2 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors text-sm">
                                                    <i class="fas fa-sign-out-alt mr-1"></i>Déconnecter
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                                                <button onclick="confirmDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')"
                                                       class="px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors text-sm">
                                                    <i class="fas fa-trash-alt mr-1"></i>Supprimer
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 space-y-4 z-50"></div>

    <!-- Modal d'ajout - Design carré simple -->
    <div id="addModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
    <div class="modal-simple">
        <div class="modal-header">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800">Nouvel administrateur</h3>
                <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form id="addForm" class="p-6">
            <div class="form-group">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" required class="form-input">
                <div id="emailFoundIndicator" style="display: none;" class="text-xs text-green-600 mt-1">
                    <i class="fas fa-check-circle mr-1"></i>Email trouvé automatiquement
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" required class="form-input" placeholder="Email sera rempli automatiquement...">
            </div>
            
            <div class="form-group">
                <label class="form-label">Rôle</label>
                <select name="role" required class="form-input">
                    <option value="admin">Administrateur</option>
                    <option value="superadmin">Super Administrateur</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" id="addPassword" required class="form-input" minlength="6">
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <input type="password" id="addPasswordConfirm" required class="form-input" minlength="6">
            </div>
            
            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="closeAddModal()" class="btn btn-secondary flex-1">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary flex-1">
                    <i class="fas fa-plus mr-2"></i>Créer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de modification - Design carré simple -->
<div id="editModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
    <div class="modal-simple">
        <div class="modal-header">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800">Modifier l'administrateur</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form id="editForm" class="p-6">
            <input type="hidden" id="editAdminId" name="id">
            
            <div class="form-group">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" id="editUsername" required class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="editEmail" required class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Rôle</label>
                <select name="role" id="editRole" required class="form-input">
                    <option value="admin">Administrateur</option>
                    <option value="superadmin">Super Administrateur</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nouveau mot de passe (optionnel)</label>
                <input type="password" name="password" id="editPassword" class="form-input" placeholder="Laisser vide pour conserver l'actuel">
            </div>
            
            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary flex-1">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary flex-1">
                    <i class="fas fa-save mr-2"></i>Modifier
                </button>
            </div>
        </form>
    </div>
</div>


    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
        <div class="modal-simple">
            <div class="modal-header">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-red-600">Confirmer la suppression</h3>
                    <button onclick="closeDeleteModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-times text-red-500 text-2xl"></i>
                </div>
                <h4 class="text-lg font-semibold text-slate-800 mb-2">Supprimer l'administrateur</h4>
                <p class="text-slate-600 mb-6">
                    Êtes-vous sûr de vouloir supprimer l'administrateur 
                    <span class="font-semibold text-red-600" id="deleteAdminName"></span> ?
                </p>
                <p class="text-sm text-slate-500 mb-6">Cette action ne peut pas être annulée.</p>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary flex-1">
                        Annuler
                    </button>
                    <button onclick="executeDelete()" class="btn flex-1" style="background: #ef4444; color: white;">
                        <i class="fas fa-trash mr-2"></i>Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de détails d'un administrateur -->
    <div id="adminDetailsModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-6 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-circle text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white" id="modalAdminName">Détails Administrateur</h3>
                            <p class="text-indigo-100 text-sm">Informations complètes et historique</p>
                        </div>
                    </div>
                    <button onclick="closeAdminDetailsModal()" class="text-white hover:text-indigo-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 max-h-[calc(90vh-120px)] overflow-y-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Informations générales -->
                    <div class="bg-slate-50 rounded-xl p-6">
                        <h4 class="font-bold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            Informations générales
                        </h4>
                        <div id="adminGeneralInfo" class="space-y-3">
                            <!-- Contenu rempli par JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Sessions actives -->
                    <div class="bg-slate-50 rounded-xl p-6">
                        <h4 class="font-bold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-wifi text-green-500 mr-2"></i>
                            Sessions actives
                        </h4>
                        <div id="adminActiveSessions" class="space-y-3 max-h-40 overflow-y-auto">
                            <!-- Contenu rempli par JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Historique des connexions -->
                    <div class="bg-slate-50 rounded-xl p-6">
                        <h4 class="font-bold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-history text-amber-500 mr-2"></i>
                            Historique des connexions
                        </h4>
                        <div id="adminLoginHistory" class="space-y-3 max-h-60 overflow-y-auto">
                            <!-- Contenu rempli par JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Journal d'activité -->
                    <div class="bg-slate-50 rounded-xl p-6">
                        <h4 class="font-bold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-clipboard-list text-purple-500 mr-2"></i>
                            Journal d'activité
                        </h4>
                        <div id="adminActivityLog" class="space-y-3 max-h-60 overflow-y-auto">
                            <!-- Contenu rempli par JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Journal d'activité -->
    <div id="activityLogModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-6xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Journal d'activité</h3>
                            <p class="text-purple-100 text-sm">Historique complet des actions</p>
                        </div>
                    </div>
                    <button onclick="closeActivityLogModal()" class="text-white hover:text-purple-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Filtres du journal -->
                <div class="bg-slate-50 rounded-xl p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Administrateur</label>
                            <input type="text" id="logAdminFilter" placeholder="Nom d'utilisateur..." class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Action</label>
                            <select id="logActionFilter" class="form-input">
                                <option value="">Toutes les actions</option>
                                <option value="CREATE_ADMIN">Création admin</option>
                                <option value="UPDATE_ADMIN">Modification admin</option>
                                <option value="DELETE_ADMIN">Suppression admin</option>
                                <option value="TOGGLE_STATUS">Changement statut</option>
                                <option value="FORCE_LOGOUT">Déconnexion forcée</option>
                                <option value="BULK_DELETE_ADMIN">Suppression en lot</option>
                                <option value="BULK_ROLE_CHANGE">Changement rôle en lot</option>
                                <option value="BULK_STATUS_CHANGE">Changement statut en lot</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input type="date" id="logDateFilter" class="form-input">
                        </div>
                        <div class="flex items-end">
                            <button onclick="filterActivityLogs()" class="btn btn-primary w-full">
                                <i class="fas fa-filter mr-1"></i>Filtrer
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des logs -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div id="activityLogsContent" class="max-h-96 overflow-y-auto">
                        <!-- Contenu rempli par JavaScript -->
                    </div>
                </div>
                
                <!-- Pagination -->
                <div id="logsPagination" class="flex items-center justify-between mt-4">
                    <!-- Contenu rempli par JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let deleteAdminId = null;
        let isDarkMode = localStorage.getItem('darkMode') === 'true';
        let isGridView = false;
        let selectedAdmins = [];
        let currentActivityPage = 1;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            if (isDarkMode) {
                toggleDarkMode();
            }
            setupFormHandlers();
            setupFilters();
            updateBulkActions();
        });

        // Configuration des gestionnaires de formulaires
        function setupFormHandlers() {
            // Gestionnaire pour l'auto-complétion de l'email
            const addUsernameInput = document.querySelector('#addModal input[name="username"]');
            const emailInput = document.querySelector('#addModal input[name="email"]');
            const emailIndicator = document.getElementById('emailFoundIndicator');
            let emailTimeout;
            
            if (addUsernameInput && emailInput) {
                addUsernameInput.addEventListener('input', function(e) {
                    const username = e.target.value.trim();
                    
                    clearTimeout(emailTimeout);
                    emailInput.value = '';
                    emailInput.removeAttribute('readonly');
                    emailIndicator.style.display = 'none';
                    
                    if (username.length >= 2) {
                        emailTimeout = setTimeout(() => {
                            fetchEmployeeEmail(username, emailInput, emailIndicator);
                        }, 500);
                    }
                });
            }

            // Formulaire d'ajout
            document.getElementById('addForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = document.getElementById('addPassword').value;
                const passwordConfirm = document.getElementById('addPasswordConfirm').value;
                
                if (password !== passwordConfirm) {
                    showToast('Les mots de passe ne correspondent pas !', 'error');
                    return;
                }
                
                if (password.length < 6) {
                    showToast('Le mot de passe doit contenir au moins 6 caractères !', 'error');
                    return;
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_admin');
                
                submitForm(formData, 'Administrateur créé avec succès');
            });

            // Formulaire de modification
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'edit_admin');
                
                submitForm(formData, 'Administrateur modifié avec succès');
            });
        }

        // Récupération de l'email employé
        function fetchEmployeeEmail(username, emailInput, emailIndicator) {
            if (!username || username.length < 2) return;
            
            const formData = new FormData();
            formData.append('action', 'get_employee_email');
            formData.append('username', username);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    emailInput.value = data.email;
                    emailInput.setAttribute('readonly', true);
                    emailIndicator.style.display = 'inline';
                    showToast('Email trouvé pour cet employé', 'success');
                } else {
                    emailInput.removeAttribute('readonly');
                    emailInput.placeholder = 'Email non trouvé, saisissez manuellement';
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération de l\'email:', error);
                emailInput.removeAttribute('readonly');
                emailInput.placeholder = 'Erreur, saisissez manuellement';
            });
        }

        // Soumission AJAX des formulaires
        function submitForm(formData, successMessage) {
            const button = document.querySelector('form button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>En cours...';
            button.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || successMessage, 'success');
                    closeAllModals();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Erreur lors de l\'opération', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        // Configuration des filtres
        function setupFilters() {
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const onlineFilter = document.getElementById('onlineFilter');
            
            if (searchInput) searchInput.addEventListener('input', filterTable);
            if (roleFilter) roleFilter.addEventListener('change', filterTable);
            if (statusFilter) statusFilter.addEventListener('change', filterTable);
            if (onlineFilter) onlineFilter.addEventListener('change', filterTable);
        }

        // Filtrage des administrateurs
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const onlineFilter = document.getElementById('onlineFilter').value;
            
            const rows = document.querySelectorAll('.admin-row, .admin-row-grid');

            rows.forEach(row => {
                const username = row.dataset.username;
                const email = row.dataset.email;
                const role = row.dataset.role;
                const status = row.dataset.status;
                const online = row.dataset.online;

                const matchesSearch = !searchTerm || 
                    username.includes(searchTerm) || 
                    email.includes(searchTerm);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesOnline = !onlineFilter || online === onlineFilter;

                if (matchesSearch && matchesRole && matchesStatus && matchesOnline) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Basculer entre vue tableau et grille
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
            
            // Réinitialiser les sélections
            clearSelection();
        }

        // ===== FONCTIONNALITÉS D'ACTIONS EN LOT =====
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.admin-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedAdmins = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            if (selectedAdmins.length > 0) {
                bulkActions.classList.remove('hidden');
                bulkActions.classList.add('show');
                selectedCount.textContent = `${selectedAdmins.length} sélectionné(s)`;
            } else {
                bulkActions.classList.add('hidden');
                bulkActions.classList.remove('show');
            }
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.admin-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActions();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.admin-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
            updateBulkActions();
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.admin-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
            updateBulkActions();
        }

        function bulkRoleChange() {
            const roleSelect = document.getElementById('bulkRoleSelect');
            const newRole = roleSelect.value;
            
            if (!newRole || selectedAdmins.length === 0) {
                showToast('Veuillez sélectionner un rôle et des administrateurs', 'warning');
                return;
            }

            if (confirm(`Changer le rôle de ${selectedAdmins.length} administrateur(s) vers "${newRole}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'bulk_role_change');
                formData.append('ids', JSON.stringify(selectedAdmins));
                formData.append('role', newRole);
                
                submitBulkAction(formData, 'Rôles mis à jour avec succès');
            }
        }

        function bulkStatusChange(status) {
            if (selectedAdmins.length === 0) {
                showToast('Veuillez sélectionner des administrateurs', 'warning');
                return;
            }

            const statusText = status ? 'activer' : 'désactiver';
            
            if (confirm(`${statusText.charAt(0).toUpperCase() + statusText.slice(1)} ${selectedAdmins.length} administrateur(s) ?`)) {
                const formData = new FormData();
                formData.append('action', 'bulk_status_change');
                formData.append('ids', JSON.stringify(selectedAdmins));
                formData.append('status', status);
                
                submitBulkAction(formData, `Administrateurs ${status ? 'activés' : 'désactivés'} avec succès`);
            }
        }

        function bulkDelete() {
            if (selectedAdmins.length === 0) {
                showToast('Veuillez sélectionner des administrateurs', 'warning');
                return;
            }

            if (confirm(`Supprimer définitivement ${selectedAdmins.length} administrateur(s) ? Cette action est irréversible.`)) {
                const formData = new FormData();
                formData.append('action', 'bulk_delete');
                formData.append('ids', JSON.stringify(selectedAdmins));
                
                submitBulkAction(formData, 'Administrateurs supprimés avec succès');
            }
        }

        function submitBulkAction(formData, successMessage) {
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(successMessage, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Erreur lors de l\'opération groupée', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            });
        }

        // ===== FONCTIONNALITÉS DE SESSIONS =====
        
        function forceLogout(adminId, adminName) {
            if (confirm(`Forcer la déconnexion de ${adminName} ? Cela fermera toutes ses sessions actives.`)) {
                const formData = new FormData();
                formData.append('action', 'force_logout');
                formData.append('admin_id', adminId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${adminName} a été déconnecté`, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Erreur lors de la déconnexion', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showToast('Erreur de connexion', 'error');
                });
            }
        }

        // ===== MODAL DE DÉTAILS ADMINISTRATEUR =====
        
        function viewAdminDetails(adminId) {
            const formData = new FormData();
            formData.append('action', 'get_admin_details');
            formData.append('admin_id', adminId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateAdminDetailsModal(data);
                    showModal('adminDetailsModal');
                } else {
                    showToast('Erreur lors du chargement des détails', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            });
        }

        function populateAdminDetailsModal(data) {
            const { admin, active_sessions, login_history, activity_log } = data;
            
            // Nom dans le header
            document.getElementById('modalAdminName').textContent = `${admin.username} - Détails`;
            
            // Informations générales avec mot de passe
            const generalInfo = document.getElementById('adminGeneralInfo');
            generalInfo.innerHTML = `
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">ID:</span>
                    <span class="text-slate-800">${admin.id}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Nom d'utilisateur:</span>
                    <span class="text-slate-800">${admin.username}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Email:</span>
                    <span class="text-slate-800">${admin.email}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Rôle:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${admin.role === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                        ${admin.role === 'superadmin' ? 'Super Admin' : 'Admin'}
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Statut:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${admin.status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${admin.status_display}
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Mot de passe:</span>
                    <div class="password-display">${admin.password_preview || 'N/A'}...</div>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-600">Créé le:</span>
                    <span class="text-slate-800">${new Date(admin.created_at).toLocaleDateString('fr-FR')}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="font-medium text-slate-600">Dernière connexion:</span>
                    <span class="text-slate-800">${admin.last_login_display}</span>
                </div>
            `;
            
            // Sessions actives
            const activeSessions = document.getElementById('adminActiveSessions');
            if (active_sessions && active_sessions.length > 0) {
                activeSessions.innerHTML = active_sessions.map(session => `
                    <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-slate-200">
                        <div>
                            <div class="flex items-center space-x-2">
                                <div class="online-indicator"></div>
                                <span class="font-medium text-slate-800">Session active</span>
                            </div>
                            <div class="text-sm text-slate-500">IP: ${session.ip_address || 'N/A'}</div>
                            <div class="text-sm text-slate-500">Depuis: ${new Date(session.logged_in_at).toLocaleDateString('fr-FR')} ${new Date(session.logged_in_at).toLocaleTimeString('fr-FR')}</div>
                        </div>
                        <button onclick="forceLogout(${admin.id}, '${admin.username}')" class="px-2 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                            Déconnecter
                        </button>
                    </div>
                `).join('');
            } else {
                activeSessions.innerHTML = '<p class="text-slate-500 text-center py-4">Aucune session active</p>';
            }
            
            // Historique des connexions
            const loginHistory = document.getElementById('adminLoginHistory');
            if (login_history && login_history.length > 0) {
                loginHistory.innerHTML = login_history.map(login => `
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-sign-in-alt text-green-500"></i>
                                <span class="font-medium text-slate-800">Connexion</span>
                            </div>
                            <span class="text-sm text-slate-500">${new Date(login.logged_in_at).toLocaleDateString('fr-FR')} ${new Date(login.logged_in_at).toLocaleTimeString('fr-FR')}</span>
                        </div>
                        <div class="mt-1 text-sm text-slate-600">
                            IP: ${login.ip_address || 'N/A'}
                            ${login.logged_out_at ? `<span class="ml-2 text-red-600">• Déconnecté le ${new Date(login.logged_out_at).toLocaleDateString('fr-FR')} ${new Date(login.logged_out_at).toLocaleTimeString('fr-FR')}</span>` : '<span class="ml-2 text-green-600">• Session active</span>'}
                        </div>
                    </div>
                `).join('');
            } else {
                loginHistory.innerHTML = '<p class="text-slate-500 text-center py-4">Aucun historique de connexion</p>';
            }
            
            // Journal d'activité
            const activityLogEl = document.getElementById('adminActivityLog');
            if (activity_log && activity_log.length > 0) {
                activityLogEl.innerHTML = activity_log.map(log => `
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-slate-800">${getActionText(log.action)}</span>
                            <span class="text-sm text-slate-500">${new Date(log.created_at).toLocaleDateString('fr-FR')} ${new Date(log.created_at).toLocaleTimeString('fr-FR')}</span>
                        </div>
                        ${log.target ? `<div class="text-sm text-slate-600">Cible: ${log.target}</div>` : ''}
                        ${log.details ? `<div class="text-sm text-slate-500">${log.details}</div>` : ''}
                        <div class="text-xs text-slate-400 mt-1">IP: ${log.ip_address || 'N/A'}</div>
                    </div>
                `).join('');
            } else {
                activityLogEl.innerHTML = '<p class="text-slate-500 text-center py-4">Aucune activité enregistrée</p>';
            }
        }

        function closeAdminDetailsModal() {
            hideModal('adminDetailsModal');
        }

        // ===== MODAL JOURNAL D'ACTIVITÉ =====
        
        function openActivityLogModal() {
            showModal('activityLogModal');
            loadActivityLogs(1);
        }

        function closeActivityLogModal() {
            hideModal('activityLogModal');
        }

        function filterActivityLogs() {
            loadActivityLogs(1);
        }

        function loadActivityLogs(page = 1) {
            currentActivityPage = page;
            
            const formData = new FormData();
            formData.append('action', 'get_activity_logs');
            formData.append('page', page);
            
            const adminFilter = document.getElementById('logAdminFilter')?.value || '';
            const actionFilter = document.getElementById('logActionFilter')?.value || '';
            const dateFilter = document.getElementById('logDateFilter')?.value || '';
            
            if (adminFilter) formData.append('admin_filter', adminFilter);
            if (actionFilter) formData.append('action_filter', actionFilter);
            if (dateFilter) formData.append('date_filter', dateFilter);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayActivityLogs(data.logs);
                    updateLogsPagination(data.page, data.total_pages, data.total);
                } else {
                    showToast('Erreur lors du chargement des logs', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            });
        }

        function displayActivityLogs(logs) {
            const container = document.getElementById('activityLogsContent');
            
            if (logs.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-slate-500">Aucune activité trouvée</div>';
                return;
            }
            
            container.innerHTML = `
                <div class="divide-y divide-slate-200">
                    ${logs.map(log => `
                        <div class="p-4 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-medium text-sm">${log.admin_username.charAt(0).toUpperCase()}</span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-slate-800">${log.admin_username}</div>
                                            <div class="text-sm text-slate-500">${new Date(log.created_at).toLocaleDateString('fr-FR')} ${new Date(log.created_at).toLocaleTimeString('fr-FR')}</div>
                                        </div>
                                    </div>
                                    <div class="ml-11">
                                        <div class="text-sm text-slate-800 mb-1">
                                            ${getActionText(log.action)}
                                            ${log.target ? `<span class="font-medium">${log.target}</span>` : ''}
                                        </div>
                                        ${log.details ? `<div class="text-sm text-slate-600 mb-1">${log.details}</div>` : ''}
                                        <div class="text-xs text-slate-400">IP: ${log.ip_address || 'N/A'}</div>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getActionColor(log.action)}">
                                        ${log.action.replace(/_/g, ' ')}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function getActionText(action) {
            const actions = {
                'CREATE_ADMIN': 'a créé l\'administrateur',
                'UPDATE_ADMIN': 'a modifié l\'administrateur',
                'DELETE_ADMIN': 'a supprimé l\'administrateur',
                'BULK_DELETE_ADMIN': 'a supprimé en lot',
                'BULK_ROLE_CHANGE': 'a changé le rôle en lot pour',
                'BULK_STATUS_CHANGE': 'a changé le statut en lot pour',
                'TOGGLE_STATUS': 'a changé le statut de',
                'FORCE_LOGOUT': 'a forcé la déconnexion de'
            };
            return actions[action] || action;
        }

        function getActionColor(action) {
            const colors = {
                'CREATE_ADMIN': 'bg-green-100 text-green-800',
                'UPDATE_ADMIN': 'bg-blue-100 text-blue-800',
                'DELETE_ADMIN': 'bg-red-100 text-red-800',
                'BULK_DELETE_ADMIN': 'bg-red-100 text-red-800',
                'BULK_ROLE_CHANGE': 'bg-purple-100 text-purple-800',
                'BULK_STATUS_CHANGE': 'bg-amber-100 text-amber-800',
                'TOGGLE_STATUS': 'bg-amber-100 text-amber-800',
                'FORCE_LOGOUT': 'bg-orange-100 text-orange-800'
            };
            return colors[action] || 'bg-slate-100 text-slate-800';
        }

        function updateLogsPagination(currentPage, totalPages, total) {
            const container = document.getElementById('logsPagination');
            
            if (totalPages <= 1) {
                container.innerHTML = `<div class="text-sm text-slate-500">Total: ${total} entrée(s)</div>`;
                return;
            }
            
            const prevDisabled = currentPage <= 1;
            const nextDisabled = currentPage >= totalPages;
            
            container.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="text-sm text-slate-500">
                        Page ${currentPage} sur ${totalPages} (${total} entrée(s))
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="loadActivityLogs(${currentPage - 1})" 
                                ${prevDisabled ? 'disabled' : ''} 
                                class="px-3 py-1 border border-slate-300 rounded text-sm ${prevDisabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-50'}">
                            <i class="fas fa-chevron-left mr-1"></i>Précédent
                        </button>
                        <button onclick="loadActivityLogs(${currentPage + 1})" 
                                ${nextDisabled ? 'disabled' : ''} 
                                class="px-3 py-1 border border-slate-300 rounded text-sm ${nextDisabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-50'}">
                            Suivant<i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            `;
        }

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

        // Gestion des modals
        function openAddModal() {
            const form = document.getElementById('addForm');
            form.reset();
            
            const emailInput = document.querySelector('#addModal input[name="email"]');
            const emailIndicator = document.getElementById('emailFoundIndicator');
            
            if (emailInput) {
                emailInput.removeAttribute('readonly');
                emailInput.placeholder = 'Email sera rempli automatiquement...';
            }
            if (emailIndicator) {
                emailIndicator.style.display = 'none';
            }
            
            showModal('addModal');
        }

        function closeAddModal() {
            hideModal('addModal');
        }

        function openEditModal(id, username, email, role) {
            document.getElementById('editAdminId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editPassword').value = '';
            showModal('editModal');
        }

        function closeEditModal() {
            hideModal('editModal');
        }

        function confirmDelete(id, username) {
            deleteAdminId = id;
            document.getElementById('deleteAdminName').textContent = username;
            showModal('deleteModal');
        }

        function closeDeleteModal() {
            hideModal('deleteModal');
            deleteAdminId = null;
        }

        function executeDelete() {
            if (!deleteAdminId) return;
            
            const formData = new FormData();
            formData.append('action', 'bulk_delete');
            formData.append('ids', JSON.stringify([deleteAdminId]));
            
            submitForm(formData, 'Administrateur supprimé avec succès');
        }

        // Fonctions utilitaires pour les modals
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        function closeAllModals() {
            ['addModal', 'editModal', 'deleteModal', 'adminDetailsModal', 'activityLogModal'].forEach(modalId => {
                hideModal(modalId);
            });
        }

        // Fermeture des modals par Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });

        // Clic à l'extérieur pour fermer les modals
        ['addModal', 'editModal', 'deleteModal', 'adminDetailsModal', 'activityLogModal'].forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideModal(modalId);
                    }
                });
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
            
            toast.className = `toast ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 max-w-sm mb-4`;
            toast.innerHTML = `
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>