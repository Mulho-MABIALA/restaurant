<?php
// ajax/add_poste.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    if (empty($_POST['nom'])) {
        throw new Exception('Le nom du poste est requis');
    }
    
    // Vérifier si le nom existe déjà
    $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
    $stmt->execute([
        'UPDATE_POSTE',
        'postes',
        $poste_id,
        json_encode(['nom' => $_POST['nom']])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Poste modifié avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
