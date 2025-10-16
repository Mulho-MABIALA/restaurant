<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Récupérer tous les plats avec leur statut de disponibilité
    $stmt = $conn->prepare("SELECT id, disponible FROM plats");
    $stmt->execute();
    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer un tableau associatif pour faciliter la comparaison
    $current_status = [];
    foreach ($plats as $plat) {
        $current_status[$plat['id']] = $plat['disponible'];
    }
    
    // Vérifier s'il y a eu des changements depuis la dernière vérification
    // Cette logique peut être adaptée selon vos besoins
    $updates_needed = false;
    
    // Si vous stockez le statut précédent en session ou en cache, vous pouvez comparer ici
    // Pour cet exemple, nous retournons simplement les données actuelles
    
    echo json_encode([
        'success' => true,
        'plats_status' => $current_status,
        'updates_needed' => $updates_needed,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la vérification : ' . $e->getMessage()
    ]);
}
?>