<?php
// admin_newsletter_toggle.php
require_once '../config.php';
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier les paramètres
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error_message'] = 'Paramètres invalides';
    header('Location: admin_newsletter.php');
    exit;
}

$id = intval($_GET['id']);
$new_status = $_GET['status'];

// Valider le statut
if (!in_array($new_status, ['actif', 'inactif'])) {
    $_SESSION['error_message'] = 'Statut invalide';
    header('Location: admin_newsletter.php');
    exit;
}

try {
    // Récupérer l'email avant modification
    $stmt = $conn->prepare("SELECT email, statut FROM newsletter WHERE id = ?");
    $stmt->execute([$id]);
    $subscriber = $stmt->fetch();
    
    if (!$subscriber) {
        $_SESSION['error_message'] = 'Abonné introuvable';
        header('Location: admin_newsletter.php');
        exit;
    }
    
    // Modifier le statut
    $updateStmt = $conn->prepare("UPDATE newsletter SET statut = ? WHERE id = ?");
    $result = $updateStmt->execute([$new_status, $id]);
    
    if ($result) {
        // Log de l'action
        error_log("Changement statut newsletter par admin: " . $subscriber['email'] . " -> " . $new_status);
        
        $action = $new_status === 'actif' ? 'réactivé' : 'désactivé';
        $_SESSION['success_message'] = "L'abonné {$subscriber['email']} a été {$action} avec succès";
    } else {
        $_SESSION['error_message'] = 'Erreur lors de la modification du statut';
    }
    
} catch (PDOException $e) {
    error_log("Erreur toggle statut newsletter: " . $e->getMessage());
    $_SESSION['error_message'] = 'Erreur technique lors de la modification';
}

header('Location: admin_newsletter.php');
exit;
?>