<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$employe_id = $_SESSION['admin_id'];
$contact_id = $_GET['contact'] ?? null;

try {
    if ($contact_id) {
        // Vérifier les nouveaux messages d'un contact spécifique
        $stmt = $conn->prepare("
            SELECT COUNT(*) as new_messages,
                   MAX(created_at) as last_message_time
            FROM messages 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$contact_id, $employe_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer aussi les derniers messages pour mise à jour en temps réel
        $messages_stmt = $conn->prepare("
            SELECT m.*, e.nom as sender_name,
                   m.sender_id = ? as is_mine
            FROM messages m 
            JOIN employes e ON m.sender_id = e.id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at DESC
            LIMIT 5
        ");
        $messages_stmt->execute([$employe_id, $employe_id, $contact_id, $contact_id, $employe_id]);
        $recent_messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'new_messages' => (int)$result['new_messages'],
            'last_message_time' => $result['last_message_time'],
            'recent_messages' => $recent_messages
        ]);
        
    } else {
        // Vérifier tous les nouveaux messages
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_unread,
                   COUNT(DISTINCT sender_id) as unread_conversations,
                   MAX(created_at) as last_message_time
            FROM messages 
            WHERE receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$employe_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les conversations avec nouveaux messages
        $conversations_stmt = $conn->prepare("
            SELECT DISTINCT m.sender_id, e.nom as sender_name, 
                   COUNT(*) as unread_count,
                   MAX(m.created_at) as last_message_time
            FROM messages m
            JOIN employes e ON m.sender_id = e.id
            WHERE m.receiver_id = ? AND m.is_read = FALSE
            GROUP BY m.sender_id, e.nom
            ORDER BY last_message_time DESC
        ");
        $conversations_stmt->execute([$employe_id]);
        $unread_conversations = $conversations_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'total_unread' => (int)$result['total_unread'],
            'unread_conversations' => (int)$result['unread_conversations'],
            'last_message_time' => $result['last_message_time'],
            'conversations' => $unread_conversations
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Erreur check_new_messages: " . $e->getMessage());
    echo json_encode(['error' => 'database_error']);
}
?>