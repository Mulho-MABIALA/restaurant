// === annonce_reaction.php - Gestion des réactions ===
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
$reaction_type = $_POST['reaction_type'] ?? '';

if (!$annonce_id || !in_array($reaction_type, ['like', 'love', 'important', 'question'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

try {
    // Vérifier si l'utilisateur a déjà réagi
    $check = $conn->prepare("SELECT id FROM annonce_reactions WHERE annonce_id = ? AND user_id = ?");
    $check->execute([$annonce_id, $user_id]);
    $existing = $check->fetch();
    
    if ($existing) {
        // Supprimer la réaction existante
        $delete = $conn->prepare("DELETE FROM annonce_reactions WHERE annonce_id = ? AND user_id = ?");
        $delete->execute([$annonce_id, $user_id]);
        $action = 'removed';
    } else {
        // Ajouter nouvelle réaction
        $insert = $conn->prepare("INSERT INTO annonce_reactions (annonce_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $insert->execute([$annonce_id, $user_id, $reaction_type]);
        $action = 'added';
    }
    
    echo json_encode(['success' => true, 'action' => $action]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
}
