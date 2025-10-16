<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    // Marquer toutes les réservations comme lues
    $stmt = $conn->prepare("UPDATE reservations SET statut = 'lu' WHERE statut = 'non_lu'");
    $stmt->execute();

    $affected = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "$affected réservation(s) marquée(s) comme lue(s)",
        'count' => $affected
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour'
    ]);
}
