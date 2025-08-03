

// === annonce_get.php - Récupérer une annonce pour édition ===
<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM annonces WHERE id = ?");
    $stmt->execute([$id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$annonce) {
        echo json_encode(['success' => false, 'message' => 'Annonce introuvable']);
        exit;
    }
    
    echo json_encode(['success' => true, 'annonce' => $annonce]);
    
} catch (PDOException $e) {
    error_log("Erreur annonce_get: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
}