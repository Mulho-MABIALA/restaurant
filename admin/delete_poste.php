<?php
// ajax/delete_poste.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (empty($input['id'])) {
        throw new Exception('ID poste requis');
    }
    
    $poste_id = $input['id'];
    
    // Vérifier si des employés sont associés à ce poste
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE poste_id = ? AND statut != 'inactif'");
    $stmt->execute([$poste_id]);
    $nb_employees = $stmt->fetchColumn();
    
    if ($nb_employees > 0) {
        throw new Exception("Impossible de supprimer ce poste car $nb_employees employé(s) y sont associé(s)");
    }
    
    // Désactivation logique du poste
    $stmt = $conn->prepare("UPDATE postes SET actif = FALSE WHERE id = ?");
    $stmt->execute([$poste_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Poste non trouvé');
    }
    
    // Log de l'activité
    $stmt = $conn->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'DELETE_POSTE',
        'postes',
        $poste_id,
        json_encode(['actif' => false])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Poste supprimé avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
