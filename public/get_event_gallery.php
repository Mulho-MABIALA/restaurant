<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($eventId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les photos de la galerie
    $query = "SELECT image, legende FROM evenements_galerie WHERE evenement_id = :event_id ORDER BY ordre ASC, id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'photos' => $photos
    ]);

} catch (PDOException $e) {
    error_log("Erreur SQL : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de chargement']);
}
?>
