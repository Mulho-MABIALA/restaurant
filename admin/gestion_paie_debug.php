<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

try {
    echo "1. Chargement config...<br>";
    require_once '../config.php';
    echo "✓ Config OK<br><br>";

    echo "2. Chargement des classes...<br>";
    require_once 'classes/EmployeesManager.php';
    echo "✓ EmployeesManager<br>";
    require_once 'classes/PresenceManager.php';
    echo "✓ PresenceManager<br>";
    require_once 'classes/PaieManager.php';
    echo "✓ PaieManager<br>";
    require_once 'classes/PostesManager.php';
    echo "✓ PostesManager<br>";
    require_once 'classes/SecurityManager.php';
    echo "✓ SecurityManager<br>";
    require_once 'classes/AuditManager.php';
    echo "✓ AuditManager<br>";
    require_once 'classes/PayrollCalculator.php';
    echo "✓ PayrollCalculator<br>";
    require_once 'classes/BulletinPDFGenerateur.php';
    echo "✓ BulletinPDFGenerateur<br><br>";

    echo "3. Instanciation des managers...<br>";
    $employeesManager = new EmployeesManager($conn);
    echo "✓ EmployeesManager instancié<br>";
    $presenceManager = new PresenceManager($conn);
    echo "✓ PresenceManager instancié<br>";
    $paieManager = new PaieManager($conn);
    echo "✓ PaieManager instancié<br>";
    $postesManager = new PostesManager($conn);
    echo "✓ PostesManager instancié<br>";
    $auditManager = new AuditManager($conn);
    echo "✓ AuditManager instancié<br>";
    $payrollCalculator = new PayrollCalculator($conn);
    echo "✓ PayrollCalculator instancié<br><br>";

    echo "4. Récupération des données...<br>";
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);
    echo "✓ Employés récupérés: " . count($employes) . "<br>";

    $bulletins = $paieManager->getBulletins(['mois' => date('n'), 'annee' => date('Y')]);
    echo "✓ Bulletins récupérés: " . count($bulletins) . "<br>";

    $statsEmployes = $employeesManager->getEmployeeStatistics();
    echo "✓ Stats employés récupérées<br>";

    $statsPaie = $paieManager->getPayrollStatistics();
    echo "✓ Stats paie récupérées<br>";

    $postes = $postesManager->getAllPostes();
    echo "✓ Postes récupérés: " . count($postes) . "<br>";

    $departements = $postesManager->getDepartements();
    echo "✓ Départements récupérés: " . count($departements) . "<br>";

    $csrf_token = SecurityManager::generateCSRFToken();
    echo "✓ Token CSRF généré<br><br>";

    echo "<h2 style='color: green;'>✓ TOUT FONCTIONNE CORRECTEMENT!</h2>";
    echo "<br><a href='gestion_paie.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Accéder à la gestion de paie</a>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ ERREUR DÉTECTÉE:</h2>";
    echo "<pre style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . "\n";
    echo "Ligne: " . $e->getLine() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
?>
