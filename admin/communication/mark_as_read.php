<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$employe_id = $_SESSION['admin_id'];
$sender_id = $_POST['sender_id'] ?? null;

if (!$sender_id) {
    echo json_encode(['error' => 'missing_sender_id']);
    exit;
}

try {
    // Marquer tous les messages de cet expéditeur comme lus
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$sender_id, $employe_id]);
    
    $affected_rows = $stmt->rowCount();
    
    echo json_encode([
        'status' => 'success',
        'marked_as_read' => $affected_rows
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur mark_as_read: " . $e->getMessage());
    echo json_encode(['error' => 'database_error']);
}
?>