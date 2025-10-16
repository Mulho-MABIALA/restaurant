<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message']));
    $note = intval($_POST['note']);
    
    // Validation
    if (empty($nom) || empty($email) || empty($message) || $note < 1 || $note > 5) {
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Adresse email invalide']);
        exit;
    }
    
    // Insertion en base
    $stmt = $conn->prepare("INSERT INTO avis (nom, email, message, note, valide, date_creation) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$nom, $email, $message, $note]);
    
    echo json_encode(['success' => true, 'message' => 'Avis enregistré avec succès']);
    
} catch (Exception $e) {
    error_log("Erreur lors de l'ajout de l'avis: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
?>