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
            $canView = 1;
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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #10b981;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }
        
        .content-wrapper {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            margin: 2rem;
            overflow: hidden;
        }
        
        .page-header {
            background: var(--primary-gradient);
            padding: 2.5rem 2rem;
            color: white;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-header p {
            opacity: 0.95;
            font-size: 1rem;
            margin: 0;
        }
        
        .content-body {
            padding: 2rem;
        }
        
        .admin-selector-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .admin-selector-card h5 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .form-select {
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .permissions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .permissions-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .admin-name {
            color: #667eea;
            font-weight: 700;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-action {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: 2px solid;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-select-all {
            background: white;
            color: #667eea;
            border-color: #667eea;
        }
        
        .btn-select-all:hover {
            background: #667eea;
            color: white;
        }
        
        .btn-deselect-all {
            background: white;
            color: #64748b;
            border-color: #cbd5e1;
        }
        
        .btn-deselect-all:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        
        .category-section {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .category-section:hover {
            border-color: #667eea;
            box-shadow: var(--shadow-md);
        }
        
        .category-header {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--border-color);
        }
        
        .category-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .category-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .btn-category-toggle {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-category-toggle:hover {
            background: #5568d3;
            transform: scale(1.05);
        }
        
        .category-content {
            padding: 1rem;
        }
        
        .page-row {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .page-row:hover {
            border-color: #667eea;
            box-shadow: var(--shadow-sm);
            transform: translateX(4px);
        }
        
        .page-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .custom-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #667eea;
            transition: transform 0.2s ease;
        }
        
        .custom-checkbox:hover {
            transform: scale(1.1);
        }
        
        .page-label {
            flex: 1;
            cursor: pointer;
            font-weight: 500;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-icon {
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .page-slug {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 400;
        }
        
        .permissions-group {
            display: flex;
            gap: 0.625rem;
            margin-top: 0.875rem;
            padding-left: 38px;
            flex-wrap: wrap;
        }
        
        .permission-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .permission-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .permission-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .permission-item.create {
            border-color: #10b981;
        }
        
        .permission-item.edit {
            border-color: #f59e0b;
        }
        
        .permission-item.delete {
            border-color: #ef4444;
        }
        
        .btn-save-permissions {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1.125rem 3rem;
            border-radius: 12px;
            font-size: 1.125rem;
            font-weight: 600;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-save-permissions:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 30px rgba(16, 185, 129, 0.4);
        }
        
        .save-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
            text-align: center;
        }
        
        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .alert-modern i {
            font-size: 1.5rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h4 {
            color: #64748b;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                margin: 1rem;
                border-radius: 16px;
            }
            
            .page-header {
                padding: 1.5rem 1rem;
            }
            
            .content-body {
                padding: 1rem;
            }
            
            .permissions-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .action-buttons {
                width: 100%;
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
            }
            
            .permissions-group {
                padding-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>
                        <i class="fas fa-user-shield"></i>
                        Gestion des Droits d'Accès
                    </h1>
                    <p>Configurez les permissions d'accès aux pages pour chaque administrateur</p>
                </div>

                <div class="content-body">
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

                    <div class="admin-selector-card">
                        <h5>
                            <i class="fas fa-user-circle me-2"></i>Sélectionnez un administrateur
                        </h5>
                        <select class="form-select" id="adminSelector" onchange="selectAdmin(this.value)">
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
                            
                            <div class="permissions-header">
                                <h2 class="permissions-title">
                                    <i class="fas fa-lock"></i>
                                    Permissions pour : <span class="admin-name"><?= htmlspecialchars($selectedAdmin['username']) ?></span>
                                </h2>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-action btn-select-all" onclick="selectAllPages()">
                                        <i class="fas fa-check-double me-2"></i>Tout sélectionner
                                    </button>
                                    <button type="button" class="btn btn-action btn-deselect-all" onclick="deselectAllPages()">
                                        <i class="fas fa-times me-2"></i>Tout désélectionner
                                    </button>
                                </div>
                            </div>

                            <?php foreach ($pagesByCategory as $category => $categoryPages): ?>
                                <div class="category-section">
                                    <div class="category-header">
                                        <h3 class="category-title">
                                            <div class="category-icon">
                                                <i class="fas fa-folder"></i>
                                            </div>
                                            <?= htmlspecialchars($category) ?>
                                        </h3>
                                        <button type="button" class="btn-category-toggle" onclick="toggleCategory('<?= $category ?>')">
                                            <i class="fas fa-check me-1"></i>Tout cocher
                                        </button>
                                    </div>

                                    <div class="category-content">
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
                                            <div class="page-row" data-category="<?= $category ?>">
                                                <div class="page-checkbox-wrapper">
                                                    <input type="checkbox" 
                                                        class="custom-checkbox page-checkbox-input" 
                                                        name="pages[]" 
                                                           value="<?= htmlspecialchars($page['page_slug']) ?>"
                                                           id="page_<?= $page['id'] ?>"
                                                           data-page-id="<?= $page['id'] ?>"
                                                           <?= $isChecked ? 'checked' : '' ?>
                                                           onchange="togglePermissions(<?= $page['id'] ?>)">
                                                    <label for="page_<?= $page['id'] ?>" class="page-label">
                                                        <i class="fas <?= htmlspecialchars($page['page_icon']) ?> page-icon"></i>
                                                        <span>
                                                            <strong><?= htmlspecialchars($page['page_name']) ?></strong>
                                                            <span class="page-slug">(<?= htmlspecialchars($page['page_slug']) ?>)</span>
                                                        </span>
                                                    </label>
                                                </div>
                                                
                                                <div class="permissions-group" id="perms_<?= $page['id'] ?>" style="display: <?= $isChecked ? 'flex' : 'none' ?>">
                                                    <div class="permission-item create">
                                                        <input type="checkbox" 
                                                               name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][create]"
                                                               <?= $canCreate ? 'checked' : '' ?>>
                                                        <span>Créer</span>
                                                    </div>
                                                    <div class="permission-item edit">
                                                        <input type="checkbox" 
                                                               name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][edit]"
                                                               <?= $canEdit ? 'checked' : '' ?>>
                                                        <span>Modifier</span>
                                                    </div>
                                                    <div class="permission-item delete">
                                                        <input type="checkbox" 
                                                               name="permissions[<?= htmlspecialchars($page['page_slug']) ?>][delete]"
                                                               <?= $canDelete ? 'checked' : '' ?>>
                                                        <span>Supprimer</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="save-section">
                                <button type="submit" name="save_permissions" class="btn-save-permissions">
                                    <i class="fas fa-save me-2"></i>Enregistrer les permissions
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-circle"></i>
                            <h4>Sélectionnez un administrateur pour gérer ses permissions</h4>
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

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>