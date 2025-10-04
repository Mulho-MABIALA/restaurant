<?php
session_start();
require_once '../../config.php';

error_reporting(0);
ini_set('display_errors', 0);

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit(json_encode(['error' => 'Non autorisé']));
}

if (!isset($_GET['admin_id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'ID administrateur manquant']));
}

$admin_id = intval($_GET['admin_id']);

// Récupérer les informations de l'admin (PDO)
$query = "SELECT username, role FROM admin WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    http_response_code(404);
    exit(json_encode(['error' => 'Administrateur non trouvé']));
}

// Si c'est un superadmin, retourner toutes les permissions à 1
if ($admin['role'] === 'superadmin') {
    $pages = [
        'dashboard', 'reservations', 'commandes', 'gestion_plats', 'categories_plats',
        'gestion_cuisine', 'gallery', 'admin_evenements', 'horaires', 'admin_newsletter',
        'avis_admin', 'finances_dashboard', 'facturation', 'tresorerie', 'rapports_finance',
        'annonces', 'annonces_public', 'add_procedure', 'voir_annonce', 'procedures',
        'incidents', 'gestion_stock', 'gestion_employe', 'gestion_postes',
        'planification_horaires', 'gestion_paie', 'badgeuse', 'presence', 'generate_badge',
        'admin_gestion', 'gestion_droits', 'statistiques'
    ];
    
    $all_permissions = [];
    foreach ($pages as $page) {
        $all_permissions[$page] = [
            'can_view' => 1,
            'can_create' => 1,
            'can_edit' => 1,
            'can_delete' => 1
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'admin_name' => $admin['username'],
        'admin_role' => $admin['role'],
        'is_superadmin' => true,
        'permissions' => $all_permissions
    ]);
    exit;
}

// Récupérer les permissions individuelles (PDO)
$query = "SELECT page_slug, can_view, can_create, can_edit, can_delete 
          FROM admin_permissions 
          WHERE admin_id = ?";
$stmt = $conn->prepare($query);
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

header('Content-Type: application/json');
echo json_encode([
    'admin_name' => $admin['username'],
    'admin_role' => $admin['role'],
    'is_superadmin' => false,
    'permissions' => $permissions
]);