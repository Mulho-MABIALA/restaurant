<?php
// includes/permissions.php

/**
 * Vérifie si un utilisateur a accès à une page
 */
function canAccess($conn, $adminId, $pageSlug) {
    if (!$adminId) return false;
    
    // Vérifier si l'admin est superadmin
    $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && $admin['role'] === 'superadmin') {
        return true; // Accès complet
    }
    
    // Vérifier les permissions spécifiques
    $stmt = $conn->prepare("
        SELECT can_view 
        FROM admin_permissions 
        WHERE admin_id = ? AND page_slug = ?
    ");
    $stmt->execute([$adminId, $pageSlug]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $perm ? (bool)$perm['can_view'] : false;
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
    foreach ($pages as $page) {
        if (canAccess($conn, $adminId, $page)) {
            return true;
        }
    }
    return false;
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