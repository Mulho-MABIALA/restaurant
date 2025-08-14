
<?php
// ajax/get_employees.php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT * FROM vue_employes_complet ORDER BY nom, prenom");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'employees' => $employees
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du chargement des employés'
    ]);
}
?>