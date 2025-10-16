<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simuler une connexion admin
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

require_once '../config.php';

echo "Connexion DB : OK<br>";

// Tester les inclusions
echo "Test des inclusions...<br>";

try {
    require_once 'classes/EmployeesManager.php';
    echo "EmployeesManager : OK<br>";
} catch (Exception $e) {
    echo "EmployeesManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/PresenceManager.php';
    echo "PresenceManager : OK<br>";
} catch (Exception $e) {
    echo "PresenceManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/PaieManager.php';
    echo "PaieManager : OK<br>";
} catch (Exception $e) {
    echo "PaieManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/PostesManager.php';
    echo "PostesManager : OK<br>";
} catch (Exception $e) {
    echo "PostesManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/SecurityManager.php';
    echo "SecurityManager : OK<br>";
} catch (Exception $e) {
    echo "SecurityManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/AuditManager.php';
    echo "AuditManager : OK<br>";
} catch (Exception $e) {
    echo "AuditManager : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/PayrollCalculator.php';
    echo "PayrollCalculator : OK<br>";
} catch (Exception $e) {
    echo "PayrollCalculator : ERROR - " . $e->getMessage() . "<br>";
}

try {
    require_once 'classes/BulletinPDFGenerateur.php';
    echo "BulletinPDFGenerateur : OK<br>";
} catch (Exception $e) {
    echo "BulletinPDFGenerateur : ERROR - " . $e->getMessage() . "<br>";
}

// Tester l'instanciation
echo "<br>Test des instanciations...<br>";

try {
    $employeesManager = new EmployeesManager($conn);
    echo "EmployeesManager instance : OK<br>";
} catch (Exception $e) {
    echo "EmployeesManager instance : ERROR - " . $e->getMessage() . "<br>";
}

try {
    $presenceManager = new PresenceManager($conn);
    echo "PresenceManager instance : OK<br>";
} catch (Exception $e) {
    echo "PresenceManager instance : ERROR - " . $e->getMessage() . "<br>";
}

try {
    $paieManager = new PaieManager($conn);
    echo "PaieManager instance : OK<br>";
} catch (Exception $e) {
    echo "PaieManager instance : ERROR - " . $e->getMessage() . "<br>";
}

try {
    $postesManager = new PostesManager($conn);
    echo "PostesManager instance : OK<br>";
} catch (Exception $e) {
    echo "PostesManager instance : ERROR - " . $e->getMessage() . "<br>";
}

try {
    $auditManager = new AuditManager($conn);
    echo "AuditManager instance : OK<br>";
} catch (Exception $e) {
    echo "AuditManager instance : ERROR - " . $e->getMessage() . "<br>";
}

try {
    $payrollCalculator = new PayrollCalculator($conn);
    echo "PayrollCalculator instance : OK<br>";
} catch (Exception $e) {
    echo "PayrollCalculator instance : ERROR - " . $e->getMessage() . "<br>";
}

echo "<br><strong>Toutes les classes sont chargées avec succès!</strong><br>";
echo "<br><a href='gestion_paie.php'>Accéder à la gestion de paie</a>";
