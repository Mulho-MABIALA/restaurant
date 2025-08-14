<?php
// ajax/mark_presence.php - Marquer la présence d'un employé
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (empty($input['employee_id'])) {
        throw new Exception('ID employé requis');
    }
    
    $employee_id = $input['employee_id'];
    $status = $input['status'] ?? 'present';
    $date = date('Y-m-d');
    $heure = date('H:i:s');
    
    // Vérifier si une présence existe déjà aujourd'hui
    $stmt = $conn->prepare("SELECT id FROM presences WHERE employe_id = ? AND date_presence = ?");
    $stmt->execute([$employee_id, $date]);
    
    if ($stmt->fetch()) {
        // Mise à jour
        $stmt = $conn->prepare("
            UPDATE presences 
            SET statut = ?, heure_arrivee = COALESCE(heure_arrivee, ?)
            WHERE employe_id = ? AND date_presence = ?
        ");
        $stmt->execute([$status, $heure, $employee_id, $date]);
    } else {
        // Insertion
        $stmt = $conn->prepare("
            INSERT INTO presences (employe_id, date_presence, heure_arrivee, statut) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $date, $heure, $status]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Présence marquée avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>