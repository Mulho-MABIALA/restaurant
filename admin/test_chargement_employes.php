<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;

require_once '../config.php';

echo "<h1>Test de chargement des employés</h1>";
echo "<style>body{font-family:Arial;padding:20px;} pre{background:#f5f5f5;padding:15px;border-left:4px solid #4CAF50;overflow-x:auto;}</style>";

// Test 1: Chargement direct
echo "<h2>Test 1: Requête SQL directe</h2>";
try {
    $sql = "
        SELECT e.*,
            p.nom as poste_nom,
            p.couleur as poste_couleur,
            p.salaire_base as poste_salaire,
            d.nom as departement_nom
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE e.statut = 'actif'
        ORDER BY e.nom, e.prenom
        LIMIT 5
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Nombre d'employés trouvés: " . count($employes) . "</strong></p>";

    if (count($employes) > 0) {
        echo "<pre>";
        print_r($employes);
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>Aucun employé trouvé avec la requête SQL!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>ERREUR SQL: " . $e->getMessage() . "</p>";
}

// Test 2: Via EmployeesManager
echo "<h2>Test 2: Via EmployeesManager</h2>";
try {
    require_once 'classes/EmployeesManager.php';
    $employeesManager = new EmployeesManager($conn);

    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);

    echo "<p><strong>Nombre d'employés via EmployeesManager: " . count($employes) . "</strong></p>";

    if (count($employes) > 0) {
        echo "<pre>";
        print_r(array_slice($employes, 0, 2)); // Afficher seulement 2 pour ne pas surcharger
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>Aucun employé retourné par EmployeesManager!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>ERREUR Manager: " . $e->getMessage() . "</p>";
}

// Test 3: Après sanitization
echo "<h2>Test 3: Après SecurityManager::sanitizeOutput()</h2>";
try {
    require_once 'classes/SecurityManager.php';

    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);
    echo "<p>Avant sanitization: " . count($employes) . " employés</p>";

    $employes_sanitized = SecurityManager::sanitizeOutput($employes);
    echo "<p>Après sanitization: " . count($employes_sanitized) . " employés</p>";

    if (count($employes_sanitized) > 0) {
        echo "<pre>";
        print_r(array_slice($employes_sanitized, 0, 1)); // Afficher seulement 1
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>ERREUR Sanitization: " . $e->getMessage() . "</p>";
}

// Test 4: Test des statistiques
echo "<h2>Test 4: Statistiques</h2>";
try {
    $stats = $employeesManager->getEmployeeStatistics();
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red;'>ERREUR Stats: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='gestion_paie.php' style='padding:10px 20px;background:#4CAF50;color:white;text-decoration:none;border-radius:5px;'>Retour à la gestion de paie</a></p>";
?>
