<?php
class PermissionManager {
    private $conn; // Connexion PDO
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Vérifie si un admin a accès à une page
     */
    public function hasAccess($admin_id, $page_slug, $permission_type = 'can_view') {
        // Protection contre les valeurs nulles
        if ($admin_id === null) {
            return false;
        }
        
        // Super admin a tous les droits
        $query = "SELECT role FROM admin WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $admin['role'] === 'superadmin') {
            return true;
        }
        
        // Vérifier les permissions individuelles d'abord
        $query = "SELECT {$permission_type} FROM admin_permissions 
                  WHERE admin_id = ? AND page_slug = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id, $page_slug]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($perm) {
            return (bool)$perm[$permission_type];
        }
        
        // Si pas de permission individuelle, vérifier les permissions du rôle
        $query = "SELECT rp.{$permission_type} 
                  FROM role_permissions rp
                  INNER JOIN admin a ON a.role_id = rp.role_id
                  WHERE a.id = ? AND rp.page_slug = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id, $page_slug]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($perm) {
            return (bool)$perm[$permission_type];
        }
        
        return false;
    }
    
    /**
     * Récupère toutes les pages accessibles par un admin
     */
    public function getAccessiblePages($admin_id) {
        $query = "SELECT role FROM admin WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $admin['role'] === 'superadmin') {
            // Super admin voit toutes les pages
            $query = "SELECT * FROM system_pages WHERE is_active = 1 ORDER BY display_order";
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Pour les autres, récupérer les pages autorisées
        $query = "SELECT DISTINCT p.* FROM system_pages p
                  LEFT JOIN admin_permissions ap ON p.page_slug = ap.page_slug AND ap.admin_id = ?
                  LEFT JOIN role_permissions rp ON p.page_slug = rp.page_slug 
                  LEFT JOIN admin a ON a.role_id = rp.role_id AND a.id = ?
                  WHERE p.is_active = 1 
                  AND (ap.can_view = 1 OR rp.can_view = 1)
                  ORDER BY p.display_order";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id, $admin_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Attribuer une permission à un admin
     */
    public function grantPermission($admin_id, $page_slug, $permissions = []) {
        $can_view = isset($permissions['can_view']) ? $permissions['can_view'] : 1;
        $can_create = isset($permissions['can_create']) ? $permissions['can_create'] : 0;
        $can_edit = isset($permissions['can_edit']) ? $permissions['can_edit'] : 0;
        $can_delete = isset($permissions['can_delete']) ? $permissions['can_delete'] : 0;
        
        $query = "INSERT INTO admin_permissions (admin_id, page_slug, can_view, can_create, can_edit, can_delete)
                  VALUES (?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                  can_view = VALUES(can_view),
                  can_create = VALUES(can_create),
                  can_edit = VALUES(can_edit),
                  can_delete = VALUES(can_delete)";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([$admin_id, $page_slug, $can_view, $can_create, $can_edit, $can_delete]);
    }
    
    /**
     * Révoquer une permission
     */
    public function revokePermission($admin_id, $page_slug) {
        $query = "DELETE FROM admin_permissions WHERE admin_id = ? AND page_slug = ?";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([$admin_id, $page_slug]);
    }
    
    /**
     * Obtenir toutes les permissions d'un admin
     */
    public function getAdminPermissions($admin_id) {
        $query = "SELECT page_slug, can_view, can_create, can_edit, can_delete 
                  FROM admin_permissions 
                  WHERE admin_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id]);
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['page_slug']] = [
                'can_view' => (int)$row['can_view'],
                'can_create' => (int)$row['can_create'],
                'can_edit' => (int)$row['can_edit'],
                'can_delete' => (int)$row['can_delete']
            ];
        }
        
        return $permissions;
    }
    
    /**
     * Copier les permissions d'un admin vers un autre
     */
    public function copyPermissions($source_admin_id, $target_admin_id) {
        $query = "INSERT INTO admin_permissions 
                  (admin_id, page_slug, can_view, can_create, can_edit, can_delete)
                  SELECT ?, page_slug, can_view, can_create, can_edit, can_delete 
                  FROM admin_permissions WHERE admin_id = ?
                  ON DUPLICATE KEY UPDATE 
                  can_view = VALUES(can_view),
                  can_create = VALUES(can_create),
                  can_edit = VALUES(can_edit),
                  can_delete = VALUES(can_delete)";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([$target_admin_id, $source_admin_id]);
    }
    
    /**
     * Réinitialiser toutes les permissions d'un admin
     */
    public function resetPermissions($admin_id) {
        $query = "DELETE FROM admin_permissions WHERE admin_id = ?";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([$admin_id]);
    }
}
?>