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
    $stmt->execute([$_POST['nom']]);
    if ($stmt->fetch()) {
        throw new Exception('Un poste avec ce nom existe déjà');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO postes (nom, description, salaire_min, salaire_max, couleur) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['nom'],
        $_POST['description'] ?? null,
        $_POST['salaire_min'] ?? 0,
        $_POST['salaire_max'] ?? 0,
        $_POST['couleur'] ?? '#3B82F6'
    ]);
    
    $poste_id = $conn->lastInsertId();
    
    // Log de l'activité
    $stmt = $conn->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'CREATE_POSTE',
        'postes',
        $poste_id,
        json_encode(['nom' => $_POST['nom']])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Poste ajouté avec succès',
        'poste_id' => $poste_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>