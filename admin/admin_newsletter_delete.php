<?php
// admin_newsletter_delete.php
require_once '../config.php';
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'ID invalide';
    header('Location: admin_newsletter.php');
    exit;
}

$id = intval($_GET['id']);

try {
    // Récupérer l'email avant suppression (pour le log)
    $stmt = $conn->prepare("SELECT email FROM newsletter WHERE id = ?");
    $stmt->execute([$id]);
    $subscriber = $stmt->fetch();
    
    if (!$subscriber) {
        $_SESSION['error_message'] = 'Abonné introuvable';
        header('Location: admin_newsletter.php');
        exit;
    }
    
    // Supprimer l'abonné
    $deleteStmt = $conn->prepare("DELETE FROM newsletter WHERE id = ?");
    $result = $deleteStmt->execute([$id]);
    
    if ($result) {
        // Log de la suppression
        error_log("Suppression newsletter par admin: " . $subscriber['email']);
        
        $_SESSION['success_message'] = 'Abonné supprimé avec succès';
    } else {
        $_SESSION['error_message'] = 'Erreur lors de la suppression';
    }
    
} catch (PDOException $e) {
    error_log("Erreur suppression newsletter: " . $e->getMessage());
    $_SESSION['error_message'] = 'Erreur technique lors de la suppression';
}

header('Location: admin_newsletter.php');
exit;
?>