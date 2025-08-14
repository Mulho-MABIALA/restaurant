<?php
// ajax/get_statistics.php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT * FROM vue_statistiques_employes");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'statistics' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des statistiques'
    ]);
}
?>
