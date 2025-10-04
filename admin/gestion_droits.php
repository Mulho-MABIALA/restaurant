<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Vérifier que l'utilisateur est superadmin
$stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$currentUserRole = $stmt->fetchColumn();

if ($currentUserRole !== 'superadmin') {
    die("Accès refusé. Seuls les superadmins peuvent gérer les permissions.");
}

$message = '';
$messageType = '';

// Traitement de la sauvegarde des permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $adminId = (int)$_POST['admin_id'];
    $selectedPages = $_POST['pages'] ?? [];
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Supprimer toutes les permissions existantes
        $stmt = $conn->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        
        // Insérer les nouvelles permissions
        $stmt = $conn->prepare("
            INSERT INTO admin_permissions (admin_id, page_slug, can_view, can_create, can_edit, can_delete) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($selectedPages as $pageSlug) {
            $canView = 1; // Toujours true si la page est sélectionnée
            $canCreate = isset($permissions[$pageSlug]['create']) ? 1 : 0;
            $canEdit = isset($permissions[$pageSlug]['edit']) ? 1 : 0;
            $canDelete = isset($permissions[$pageSlug]['delete']) ? 1 : 0;
            
            $stmt->execute([$adminId, $pageSlug, $canView, $canCreate, $canEdit, $canDelete]);
        }
        
        $conn->commit();
        $message = "Permissions mises à jour avec succès !";
        $messageType = "success";
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "Erreur lors de la mise à jour des permissions : " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer tous les admins (sauf superadmins)
$admins = $conn->query("
    SELECT id, username, email, role 
    FROM admin 
    WHERE role != 'superadmin' 
    ORDER BY username
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les pages groupées par catégorie
$pages = $conn->query("
    SELECT * FROM admin_pages 
    WHERE is_active = 1 
    ORDER BY category, display_order, page_name
")->fetchAll(PDO::FETCH_ASSOC);

// Grouper les pages par catégorie
$pagesByCategory = [];
foreach ($pages as $page) {
    $category = $page['category'] ?: 'Autres';
    if (!isset($pagesByCategory[$category])) {
        $pagesByCategory[$category] = [];
    }
    $pagesByCategory[$category][] = $page;
}

// Si un admin est sélectionné, récupérer ses permissions
$selectedAdminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : null;
$currentPermissions = [];

if ($selectedAdminId) {
    $stmt = $conn->prepare("SELECT * FROM admin_permissions WHERE admin_id = ?");
    $stmt->execute([$selectedAdminId]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($perms as $perm) {
        $currentPermissions[$perm['page_slug']] = [
            'view' => $perm['can_view'],
            'create' => $perm['can_create'],
            'edit' => $perm['can_edit'],
            'delete' => $perm['can_delete']
        ];
    }
    
    // Récupérer les infos de l'admin sélectionné
    $stmt = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt->execute([$selectedAdminId]);
    $selectedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Droits d'Accès</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin: 2rem auto;
            max-width: 1400px;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
        }
        
        .admin-selector {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .category-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.1);
        }
        
        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
        }
        
        .page-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .page-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .page-checkbox {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .custom-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        .permission-badges {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding-left: 40px;
        }
        
        .permission-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }
        
        .permission-badge input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .select-all-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .select-all-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-modern i {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="main-container">
                <div class="header-section">
                    <h1 class="display-5 mb-3">
                        <i class="fas fa-user-shield me-3"></i>Gestion des Droits d'Accès
                    </h1>
                    <p class="lead mb-0">Configurez les permissions d'accès aux pages pour chaque administrateur</p>
                </div>

                <div class="p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-modern alert-dismissible fade show">
                            <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <div>
                                <strong><?= $messageType === 'error' ? 'Erreur' : 'Succès' ?></strong>
                                <p class="mb-0"><?= htmlspecialchars($message) ?></p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Sélection de l'administrateur -->
                    <div class="admin-selector">
                        <h5 class="text-white mb-3">
                            <i class="fas fa-user-circle me-2"></i>Sélectionnez un administrateur
                        </h5>
                        <select class="form-select form-select-lg" id="adminSelector" onchange="selectAdmin(this.value)">
                            <option value="">-- Choisir un administrateur --</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= $admin['id'] ?>" <?= $selectedAdminId == $admin['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['username']) ?> 
                                    (<?= htmlspecialchars($admin['email']) ?>) 
                                    - <?= ucfirst($admin['role']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selectedAdminId && $selectedAdmin): ?>
                        <form method="POST" id="permissionsForm">
                            <input type="hidden" name="admin_id" value="<?= $selectedAdminId ?>">
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    Permissions pour : <strong class="text-primary"><?= htmlspecialchars($selectedAdmin['username']) ?></strong>
                                </h4>
                                <div>
                                    <button type="button" class="btn btn-outline-primary me-2" onclick="selectAllPages()">
                                        <i class="fas fa-check-double me-2"></i>Tout sélectionner
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="deselectAllPages()">
                                        <i class="fas fa-times me-2"></i>Tout désélectionner
                                    </button>
                                </div>
                            </div>

                            <?php foreach ($pagesByCategory as $category => $categoryPages): ?>
                                <div class="category-card">
                                    <div class="category-header">
                                        <div class="category-icon">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                        <h5 class="mb-0 flex-grow-1"><?= htmlspecialchars($category) ?></h5>
                                        <button type="button" class="select-all-btn" onclick="toggleCategory('<?= $category ?>')">
                                            <i class="fas fa-check me-1"></i>Tout cocher
                                        </button>
                                    </div>

                                    <?php foreach ($categoryPages as $page): ?>
                                        <?php
                                        $isChecked = isset($currentPermissions[$page['page_slug']]['view']) && 
                                                    $currentPermissions[$page['page_slug']]['view'] == 1;
                                        $canCreate = isset($currentPermissions[$page['page_slug']]['create']) && 
                                                    $currentPermissions[$page['page_slug']]['create'] == 1;
                                        $canEdit = isset($currentPermissions[$page['page_slug']]['edit']) && 
                                                  $currentPermissions[$page['page_slug']]['edit'] == 1;
                                        $canDelete = isset($currentPermissions[$page['page_slug']]['delete']) && 
                                                    $currentPermissions[$page['page_slug']]['delete'] == 1;
                                        ?>
                                        <div class="page-item" data-category="<?= $category ?>">
                                            <div class="page-checkbox">
                                                <input type="checkbox" 
                                                       class="custom-checkbox page-checkbox-input" 
                                                       name="pages[]" 
                                                       value="<?= htmlspecialchars($page['page_slug']) ?>"
                                                       id="page_<?= $page['id'] ?>"
                                                       data-page-id="<?= $page['id'] ?>"
                                                       <?= $isChecked ? 'checked' : '' ?>
                                                       onchange="togglePermissions(<?= $page['id'] ?>)">
                                                <label for="page_<?= $page['id'] ?>" style="cursor: pointer; flex-grow: 1;">
                                                    <i class="fas <?= htmlspecialchars($page['page_icon']) ?> me-2 text-primary"></i>
                                                    <strong><?= htmlspecialchars($page['page_name']) ?></strong>
                                                    <small class="text-muted ms-2">(<?= htmlspecialchars($page['page_slug']) ?>)</small>
                                                </label>
                                            </div>
                                            
                                            <div class="permission-badges" id="perms_<?= $page['id'] ?>" style="display: <?= $isChecked ? 'flex' : 'none' ?>">
                                                <div class="permission-badge">
                                                    <input type="checkbox" 
                                                           name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][create]"
                                                           <?= $canCreate ? 'checked' : '' ?>>
                                                    <span>Créer</span>
                                                </div>
                                                <div class="permission-badge">
                                                    <input type="checkbox" 
                                                           name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][edit]"
                                                           <?= $canEdit ? 'checked' : '' ?>>
                                                    <span>Modifier</span>
                                                </div>
                                                <div class="permission-badge">
                                                    <input type="checkbox" 
                                                           name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][delete]"
                                                           <?= $canDelete ? 'checked' : '' ?>>
                                                    <span>Supprimer</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-center mt-4">
                                <button type="submit" name="save_permissions" class="btn-save">
                                    <i class="fas fa-save me-2"></i>Enregistrer les permissions
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-circle fa-5x text-muted mb-3"></i>
                            <h4 class="text-muted">Sélectionnez un administrateur pour gérer ses permissions</h4>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAdmin(adminId) {
            if (adminId) {
                window.location.href = `gestion_droits.php?admin_id=${adminId}`;
            }
        }

        function togglePermissions(pageId) {
            const checkbox = document.querySelector(`input[data-page-id="${pageId}"]`);
            const permsDiv = document.getElementById(`perms_${pageId}`);
            
            if (checkbox.checked) {
                permsDiv.style.display = 'flex';
            } else {
                permsDiv.style.display = 'none';
                // Décocher toutes les permissions détaillées
                permsDiv.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            }
        }

        function selectAllPages() {
            document.querySelectorAll('.page-checkbox-input').forEach(checkbox => {
                checkbox.checked = true;
                togglePermissions(checkbox.dataset.pageId);
            });
        }

        function deselectAllPages() {
            document.querySelectorAll('.page-checkbox-input').forEach(checkbox => {
                checkbox.checked = false;
                togglePermissions(checkbox.dataset.pageId);
            });
        }

        function toggleCategory(category) {
            const categoryItems = document.querySelectorAll(`[data-category="${category}"] .page-checkbox-input`);
            const allChecked = Array.from(categoryItems).every(cb => cb.checked);
            
            categoryItems.forEach(checkbox => {
                checkbox.checked = !allChecked;
                togglePermissions(checkbox.dataset.pageId);
            });
        }
    </script>
</body>
</html>