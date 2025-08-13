<?php
session_start();
require_once '../../config.php';

// SÉCURITÉ : Vérification de l'authentification et de la méthode
if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$user_id = $_SESSION['admin_id'];

try {
    // SÉCURITÉ : Mise à jour UNIQUEMENT pour l'utilisateur connecté
    $stmt = $conn->prepare("
        INSERT INTO user_status (user_id, last_activity, is_online) 
        VALUES (?, NOW(), TRUE) 
        ON DUPLICATE KEY UPDATE last_activity = NOW(), is_online = TRUE
    ");
    
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        // Marquer les autres utilisateurs hors ligne si inactifs
        $conn->query("
            UPDATE user_status 
            SET is_online = FALSE 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Échec de la mise à jour');
    }
    
} catch (Exception $e) {
    // Log sécurisé
    error_log("Erreur update_activity - User ID: {$user_id} - " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur technique']);
}
?>