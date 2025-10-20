<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $message = htmlspecialchars(trim($_POST['message']));
    $note = intval($_POST['note']);

    // Validation
    if (empty($message) || $note < 1 || $note > 5) {
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement']);
        exit;
    }

    // Insertion en base avec des valeurs anonymes
    $stmt = $conn->prepare("INSERT INTO avis (nom, email, message, note, valide, date_creation) VALUES ('Anonyme', '', ?, ?, 0, NOW())");
    $stmt->execute([$message, $note]);
    
    echo json_encode(['success' => true, 'message' => 'Avis enregistré avec succès']);
    
} catch (Exception $e) {
    error_log("Erreur lors de l'ajout de l'avis: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
?>