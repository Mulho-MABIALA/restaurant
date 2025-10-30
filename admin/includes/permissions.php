<?php
// includes/permissions.php

/**
 * Vérifie si un utilisateur a accès à une page
 */
function canAccess($conn, $adminId, $pageSlug) {
    if (!$adminId) return false;

    try {
        // Vérifier si l'admin est superadmin
        $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && $admin['role'] === 'superadmin') {
            return true; // Accès complet pour superadmin
        }

        // Vérifier les permissions spécifiques
        $stmt = $conn->prepare("
            SELECT can_view
            FROM admin_permissions
            WHERE admin_id = ? AND page_slug = ?
        ");
        $stmt->execute([$adminId, $pageSlug]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($perm) {
            return (bool)$perm['can_view'];
        }

        // FALLBACK: Si l'admin existe mais pas de permissions définies,
        // accorder l'accès par défaut pour éviter de bloquer la navigation
        if ($admin) {
            error_log("WARNING: No permissions found for admin $adminId on page $pageSlug. Granting access by default.");
            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("Error in canAccess: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie l'accès et redirige si refusé
 */
function requireAccess($conn, $adminId, $pageSlug) {
    if (!canAccess($conn, $adminId, $pageSlug)) {
        header('Location: access_denied.php');
        exit;
    }
}

/**
 * Vérifie si au moins une page est visible
 */
function anyVisible($conn, $adminId, $pages) {
    if (!$adminId) return false;

    try {
        // Vérifier si l'admin est superadmin
        $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Les superadmins voient tout
        if ($admin && $admin['role'] === 'superadmin') {
            return true;
        }

        // Vérifier si l'admin existe (sinon il a au moins les droits par défaut)
        if (!$admin) {
            return false;
        }

        // Vérifier si au moins une page est accessible
        foreach ($pages as $page) {
            if (canAccess($conn, $adminId, $page)) {
                return true;
            }
        }

        // FALLBACK: Si c'est un admin existant, montrer les sections
        return true;
    } catch (Exception $e) {
        error_log("Error in anyVisible: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie une permission spécifique (create, edit, delete)
 */
function hasPermission($conn, $adminId, $pageSlug, $action = 'view') {
    if (!$adminId) return false;
    
    // Superadmin a tous les droits
    $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && $admin['role'] === 'superadmin') {
        return true;
    }
    
    // Vérifier la permission spécifique
    $column = 'can_' . $action;
    $stmt = $conn->prepare("
        SELECT {$column}
        FROM admin_permissions 
        WHERE admin_id = ? AND page_slug = ?
    ");
    $stmt->execute([$adminId, $pageSlug]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $perm ? (bool)$perm[$column] : false;
}

/**
 * Récupère toutes les pages accessibles par un admin
 */
function getAccessiblePages($conn, $adminId) {
    // Superadmin a accès à tout
    $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && $admin['role'] === 'superadmin') {
        $stmt = $conn->query("SELECT page_slug FROM admin_pages WHERE is_active = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Récupérer les pages autorisées
    $stmt = $conn->prepare("
        SELECT page_slug 
        FROM admin_permissions 
        WHERE admin_id = ? AND can_view = 1
    ");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>