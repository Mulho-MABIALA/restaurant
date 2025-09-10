<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID employé invalide']);
    exit;
}

$employee_id = (int)$_GET['employee_id'];

try {
    // Récupérer l'historique des présences des 30 derniers jours
    $stmt = $conn->prepare("
        SELECT 
            p.date_presence as date,
            p.heure_arrivee,
            p.heure_depart,
            p.statut,
            p.notes,
            p.date_creation,
            -- Calculer le retard en minutes
            CASE 
                WHEN p.heure_arrivee IS NOT NULL AND e.heure_debut IS NOT NULL 
                THEN TIMESTAMPDIFF(
                    MINUTE, 
                    STR_TO_DATE(CONCAT(p.date_presence, ' ', e.heure_debut), '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(CONCAT(p.date_presence, ' ', p.heure_arrivee), '%Y-%m-%d %H:%i:%s')
                )
                ELSE NULL
            END as retard_minutes,
            -- Calculer les heures travaillées
            CASE 
                WHEN p.heure_arrivee IS NOT NULL AND p.heure_depart IS NOT NULL 
                THEN TIMESTAMPDIFF(
                    MINUTE, 
                    STR_TO_DATE(CONCAT(p.date_presence, ' ', p.heure_arrivee), '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(CONCAT(p.date_presence, ' ', p.heure_depart), '%Y-%m-%d %H:%i:%s')
                ) / 60
                ELSE NULL
            END as heures_travaillees
        FROM presences p
        LEFT JOIN employes e ON p.employe_id = e.id
        WHERE p.employe_id = :employee_id 
        AND p.date_presence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY p.date_presence DESC
    ");
    
    $stmt->execute(['employee_id' => $employee_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur SQL get_presence_history: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>