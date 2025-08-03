// === annonce_comment.php - Gestion des commentaires ===
<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['admin_id'];
$annonce_id = $_POST['annonce_id'] ?? 0;
$commentaire = trim($_POST['commentaire'] ?? '');

if (!$annonce_id || empty($commentaire)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO annonce_commentaires (annonce_id, user_id, commentaire) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$annonce_id, $user_id, $commentaire]);
    
    echo json_encode(['success' => true, 'message' => 'Commentaire ajouté']);
    
} catch (PDOException $e) {
    error_log("Erreur annonce_comment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
}

// === annonce_mark_read.php - Marquer comme lu ===
<?php
require_once '../../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    exit;
}

$user_id = $_SESSION['admin_id'];
$annonce_id = $_POST['annonce_id'] ?? 0;

if (!$annonce_id) {
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO annonce_lectures (annonce_id, user_id) 
        VALUES (?, ?)
    ");
    $stmt->execute([$annonce_id, $user_id]);
    
} catch (PDOException $e) {
    error_log("Erreur annonce_mark_read: " . $e->getMessage());
}
