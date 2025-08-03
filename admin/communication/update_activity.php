<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$employe_id = $_SESSION['admin_id'];

try {
    // Mettre à jour le statut d'activité de l'utilisateur
    $stmt = $conn->prepare("
        INSERT INTO user_status (user_id, last_activity, is_online) 
        VALUES (?, NOW(), TRUE) 
        ON DUPLICATE KEY UPDATE 
            last_activity = NOW(), 
            is_online = TRUE
    ");
    $stmt->execute([$employe_id]);
    
    // Marquer les utilisateurs inactifs comme hors ligne (5+ minutes d'inactivité)
    $conn->query("
        UPDATE user_status 
        SET is_online = FALSE 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    
    echo json_encode(['status' => 'success', 'updated_at' => date('Y-m-d H:i:s')]);
    
} catch (PDOException $e) {
    error_log("Erreur update_activity: " . $e->getMessage());
    echo json_encode(['error' => 'database_error']);
}
?>