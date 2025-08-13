<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messagerie.php');
    exit();
}

$sender_id = $_SESSION['admin_id'];
$receiver_id = $_POST['receiver_id'] ?? null;
$message = trim($_POST['message'] ?? '');

// SÉCURITÉ : Validation stricte
if (!$receiver_id || !is_numeric($receiver_id)) {
    $_SESSION['error'] = 'Destinataire invalide';
    header('Location: messagerie.php');
    exit();
}

// SÉCURITÉ : Vérifier que le destinataire existe et n'est pas l'expéditeur
$receiver_check = $conn->prepare("SELECT id, nom FROM employes WHERE id = ? AND id != ?");
$receiver_check->execute([$receiver_id, $sender_id]);
$receiver = $receiver_check->fetch();

if (!$receiver) {
    $_SESSION['error'] = 'Destinataire invalide ou vous ne pouvez pas vous envoyer un message';
    header('Location: messagerie.php');
    exit();
}

// Validation du message (au moins un message ou une image)
if (empty($message) && empty($_FILES['attachment']['name'])) {
    $_SESSION['error'] = 'Veuillez saisir un message ou joindre une image';
    header('Location: messagerie.php?contact=' . $receiver_id);
    exit();
}

// Traitement de l'image (UNIQUEMENT)
$attachment_path = null;
$attachment_name = null;

if (!empty($_FILES['attachment']['name'])) {
    $file = $_FILES['attachment'];
    
    // SÉCURITÉ : Validation stricte des images
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Vérifier le type MIME
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = 'Seules les images sont autorisées (JPEG, PNG, GIF, WebP)';
        header('Location: messagerie.php?contact=' . $receiver_id);
        exit();
    }
    
    // Vérifier la taille
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'L\'image est trop volumineuse (maximum 5MB)';
        header('Location: messagerie.php?contact=' . $receiver_id);
        exit();
    }
    
    // Vérifier que c'est réellement une image (sécurité supplémentaire)
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $_SESSION['error'] = 'Fichier image corrompu ou invalide';
        header('Location: messagerie.php?contact=' . $receiver_id);
        exit();
    }
    
    // Générer un nom unique et sécurisé
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = uniqid('img_' . $sender_id . '_' . $receiver_id . '_', true) . '.' . strtolower($extension);
    
    // Créer le dossier s'il n'existe pas
    $upload_dir = '../../uploads/messages/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $full_path = $upload_dir . $safe_filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        $attachment_path = 'uploads/messages/images/' . $safe_filename;
        $attachment_name = $file['name']; // Nom original pour l'affichage
        
        // SÉCURITÉ : Vérifier à nouveau le fichier uploadé
        $final_check = getimagesize($full_path);
        if ($final_check === false) {
            unlink($full_path); // Supprimer le fichier
            $_SESSION['error'] = 'Erreur lors du traitement de l\'image';
            header('Location: messagerie.php?contact=' . $receiver_id);
            exit();
        }
    } else {
        $_SESSION['error'] = 'Erreur lors de l\'upload de l\'image';
        header('Location: messagerie.php?contact=' . $receiver_id);
        exit();
    }
}

try {
    // SÉCURITÉ : Transaction pour garantir l'intégrité
    $conn->beginTransaction();
    
    // Insérer le message avec protection contre les injections SQL
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, attachment_path, attachment_name, created_at, is_read) 
        VALUES (?, ?, ?, ?, ?, NOW(), FALSE)
    ");
    
    $result = $stmt->execute([
        $sender_id,
        $receiver_id,
        $message,
        $attachment_path,
        $attachment_name
    ]);
    
    if ($result) {
        $conn->commit();
        $_SESSION['success'] = 'Message envoyé avec succès';
        
        // Log sécurisé (sans contenu du message pour la confidentialité)
        error_log("Message privé envoyé - De: ID{$sender_id} À: ID{$receiver_id} - " . date('Y-m-d H:i:s'));
        
    } else {
        $conn->rollBack();
        if ($attachment_path && file_exists('../../' . $attachment_path)) {
            unlink('../../' . $attachment_path); // Nettoyer en cas d'échec
        }
        $_SESSION['error'] = 'Erreur lors de l\'envoi du message';
    }
    
} catch (Exception $e) {
    $conn->rollBack();
    
    // Nettoyer le fichier uploadé en cas d'erreur
    if ($attachment_path && file_exists('../../' . $attachment_path)) {
        unlink('../../' . $attachment_path);
    }
    
    // Log de l'erreur (sans données sensibles)
    error_log("Erreur envoi message privé - Sender: ID{$sender_id} - " . $e->getMessage());
    $_SESSION['error'] = 'Erreur technique lors de l\'envoi';
}

// Redirection sécurisée
header('Location: messagerie.php?contact=' . $receiver_id);
exit();
?>