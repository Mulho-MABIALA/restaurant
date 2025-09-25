<?php
require_once '../config.php';

// Définir le type de contenu JSON
header('Content-Type: application/json');

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données POST
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Valider les données
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID du plat invalide']);
    exit;
}

if (!in_array($action, ['block', 'unblock'])) {
    echo json_encode(['success' => false, 'message' => 'Action invalide']);
    exit;
}

try {
    // Vérifier que le plat existe
    $stmt = $conn->prepare("SELECT nom FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $plat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plat) {
        echo json_encode(['success' => false, 'message' => 'Plat non trouvé']);
        exit;
    }
    
    // Déterminer la nouvelle valeur de disponibilité
    $nouveauStatut = ($action === 'block') ? 0 : 1;
    
    // Mettre à jour le statut du plat
    $stmt = $conn->prepare("UPDATE plats SET disponible = ? WHERE id = ?");
    $result = $stmt->execute([$nouveauStatut, $id]);
    
    if ($result) {
        $actionText = ($action === 'block') ? 'bloqué' : 'débloqué';
        $message = "Le plat \"{$plat['nom']}\" a été {$actionText} avec succès.";
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'new_status' => $nouveauStatut
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>