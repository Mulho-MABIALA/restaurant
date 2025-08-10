<?php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $admin_id = $_SESSION['admin_id'] ?? 0;
    
    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID admin non valide']);
        exit;
    }
    
    $file = $_FILES['profile_photo'];
    
    // Vérifications de sécurité
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
        exit;
    }
    
    if ($file['size'] > 2 * 1024 * 1024) { // 2MB
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 2MB)']);
        exit;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload']);
        exit;
    }
    
    // Créer le dossier uploads s'il n'existe pas
    $upload_dir = '../uploads/profiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $admin_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    try {
        // Supprimer l'ancienne photo s'il y en a une
        $stmt = $conn->prepare("SELECT profile_photo FROM admin WHERE id = ?");
        $stmt->execute([$admin_id]);
        $old_photo = $stmt->fetchColumn();
        
        if ($old_photo && file_exists($old_photo)) {
            unlink($old_photo);
        }
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Mettre à jour la base de données
            $stmt = $conn->prepare("UPDATE admin SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$upload_path, $admin_id]);
            
            // Retourner le succès avec l'URL de la photo
            echo json_encode([
                'success' => true, 
                'message' => 'Photo mise à jour avec succès',
                'photo_url' => $upload_path
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier']);
        }
        
    } catch (PDOException $e) {
        error_log("Erreur SQL lors de la mise à jour de la photo : " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
}
?>