<?php
require_once '../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_id = $_SESSION['admin_id'];
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    
    // Validation
    if (empty($receiver_id) || empty($message)) {
        header('Location: messagerie.php?error=missing_fields');
        exit;
    }
    
    // Vérifier que le destinataire existe
    $check_user = $conn->prepare("SELECT id FROM employes WHERE id = ?");
    $check_user->execute([$receiver_id]);
    if (!$check_user->fetch()) {
        header('Location: messagerie.php?error=invalid_user');
        exit;
    }
    
    $attachment_path = null;
    $attachment_name = null;
    
    // Gestion des pièces jointes
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/messages/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['attachment']['name']);
        $file_name = uniqid() . '_' . time() . '.' . $file_info['extension'];
        $attachment_path = $upload_dir . $file_name;
        $attachment_name = $_FILES['attachment']['name'];
        
        // Vérification de sécurité
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'xlsx', 'xls'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            header('Location: messagerie.php?error=invalid_file_type');
            exit;
        }
        
        if ($_FILES['attachment']['size'] > $max_size) {
            header('Location: messagerie.php?error=file_too_large');
            exit;
        }
        
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
            header('Location: messagerie.php?error=upload_failed');
            exit;
        }
    }
    
    try {
        // Insérer le message
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, attachment_path, attachment_name, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$sender_id, $receiver_id, $message, $attachment_path, $attachment_name]);
        
        // Rediriger vers la conversation
        header('Location: messagerie.php?contact=' . $receiver_id . '&success=sent');
        
    } catch (PDOException $e) {
        // En cas d'erreur, supprimer le fichier uploadé s'il existe
        if ($attachment_path && file_exists($attachment_path)) {
            unlink($attachment_path);
        }
        
        error_log("Erreur envoi message: " . $e->getMessage());
        header('Location: messagerie.php?error=database_error');
    }
    
} else {
    header('Location: messagerie.php');
}

exit;
?>