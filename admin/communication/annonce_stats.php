// === annonce_stats.php - Statistiques des annonces ===
<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$is_admin) {
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

try {
    // Statistiques générales
    $stats = [];
    
    // Nombre total d'annonces
    $total = $conn->query("SELECT COUNT(*) as total FROM annonces WHERE statut = 'publiee'")->fetch();
    $stats['total_annonces'] = $total['total'];
    
    // Annonces par importance
    $importance = $conn->query("
        SELECT importance, COUNT(*) as count 
        FROM annonces 
        WHERE statut = 'publiee' 
        GROUP BY importance
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stats['par_importance'] = $importance;
    
    // Top 5 annonces les plus vues
    $top_vues = $conn->query("
        SELECT titre, vues 
        FROM annonces 
        WHERE statut = 'publiee' 
        ORDER BY vues DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stats['top_vues'] = $top_vues;
    
    // Statistiques de lecture
    $lectures = $conn->query("
        SELECT 
            COUNT(DISTINCT a.id) as total_annonces,
            COUNT(DISTINCT al.annonce_id) as annonces_lues,
            COUNT(al.id) as total_lectures
        FROM annonces a
        LEFT JOIN annonce_lectures al ON a.id = al.annonce_id
        WHERE a.statut = 'publiee'
    ")->fetch();
    $stats['lectures'] = $lectures;
    
    // Évolution par mois (6 derniers mois)
    $evolution = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mois,
            COUNT(*) as count
        FROM annonces 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND statut = 'publiee'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mois DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stats['evolution'] = $evolution;
    
    echo json_encode($stats);
    
} catch (PDOException $e) {
    error_log("Erreur annonce_stats: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur base de données']);
}