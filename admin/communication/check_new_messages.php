<?php
session_start();
require_once '../../config.php';

// SÉCURITÉ : Vérification stricte de l'authentification
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['admin_id'];
$contact_id = $_GET['contact'] ?? null;

try {
    // SÉCURITÉ : Compter UNIQUEMENT les messages destinés à l'utilisateur connecté
    if ($contact_id && is_numeric($contact_id)) {
        // Vérifier que l'utilisateur a accès à cette conversation
        $access_check = $conn->prepare("
            SELECT COUNT(*) as count FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        ");
        $access_check->execute([$user_id, $contact_id, $contact_id, $user_id]);
        $has_access = $access_check->fetch()['count'] > 0;
        
        if (!$has_access) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé à cette conversation']);
            exit();
        }
        
        // Compter les nouveaux messages de ce contact spécifique
        $stmt = $conn->prepare("
            SELECT COUNT(*) as new_messages 
            FROM messages 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$contact_id, $user_id]);
        
    } else {
        // Compter tous les nouveaux messages pour cet utilisateur
        $stmt = $conn->prepare("
            SELECT COUNT(*) as new_messages 
            FROM messages 
            WHERE receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
    }
    
    $result = $stmt->fetch();
    
    // Mettre à jour l'activité de l'utilisateur
    $activity_stmt = $conn->prepare("
        INSERT INTO user_status (user_id, last_activity, is_online) 
        VALUES (?, NOW(), TRUE) 
        ON DUPLICATE KEY UPDATE last_activity = NOW(), is_online = TRUE
    ");
    $activity_stmt->execute([$user_id]);
    
    // Réponse sécurisée
    echo json_encode([
        'success' => true,
        'new_messages' => (int)$result['new_messages'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log de l'erreur sans exposer de données sensibles
    error_log("Erreur check_new_messages - User ID: {$user_id} - " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => 'Erreur technique']);
}
?>