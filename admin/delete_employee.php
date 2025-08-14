
<?php
// ajax/delete_employee.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (empty($input['id'])) {
        throw new Exception('ID employé requis');
    }
    
    $employee_id = $input['id'];
    
    // Désactivation logique (pas de suppression physique)
    $stmt = $conn->prepare("UPDATE employes SET statut = 'inactif' WHERE id = ?");
    $stmt->execute([$employee_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employé non trouvé');
    }
    
    // Log de l'activité
    $stmt = $conn->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'DEACTIVATE_EMPLOYEE',
        'employes',
        $employee_id,
        json_encode(['statut' => 'inactif'])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Employé désactivé avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>