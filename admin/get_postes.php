<?php
// ajax/get_postes.php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT * FROM postes WHERE actif = TRUE ORDER BY nom");
    $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'postes' => $postes
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des postes'
    ]);
}
?>