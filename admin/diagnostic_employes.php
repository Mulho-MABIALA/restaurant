<?php
session_start();
require_once '../config.php';

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostic - Récupération des employés</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// 1. Vérifier la connexion à la base de données
echo "<h2>1. Connexion à la base de données</h2>";
try {
    if (isset($conn) && $conn instanceof PDO) {
        echo "<p class='success'>✓ Connexion PDO établie</p>";
    } else {
        echo "<p class='error'>✗ Connexion PDO non établie</p>";
        die();
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur: " . $e->getMessage() . "</p>";
    die();
}

// 2. Vérifier l'existence des tables
echo "<h2>2. Vérification des tables</h2>";
$tables = ['employes', 'postes', 'departements'];
foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Table '$table' existe</p>";

            // Compter les enregistrements
            $count = $conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p class='info'>→ $count enregistrement(s) dans la table</p>";

            // Si c'est la table employes, afficher plus de détails
            if ($table === 'employes') {
                $actifs = $conn->query("SELECT COUNT(*) FROM employes WHERE statut = 'actif'")->fetchColumn();
                echo "<p class='info'>→ $actifs employé(s) actif(s)</p>";

                // Afficher la structure
                echo "<p class='info'>Structure de la table employes:</p>";
                $columns = $conn->query("DESCRIBE employes")->fetchAll(PDO::FETCH_ASSOC);
                echo "<pre style='background:#f0f0f0;padding:10px;'>";
                foreach ($columns as $col) {
                    echo sprintf("%-25s %-20s %s\n", $col['Field'], $col['Type'], $col['Null']);
                }
                echo "</pre>";
            }
        } else {
            echo "<p class='error'>✗ Table '$table' n'existe pas</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erreur pour la table '$table': " . $e->getMessage() . "</p>";
    }
}

// 3. Tester la requête de getAllEmployees
echo "<h2>3. Test de la requête getAllEmployees</h2>";
try {
    $sql = "
        SELECT e.*,
            p.nom as poste_nom,
            p.couleur as poste_couleur,
            p.salaire as poste_salaire,
            d.nom as departement_nom,
            d.id as departement_id
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE e.statut = 'actif'
        ORDER BY e.nom, e.prenom
    ";

    echo "<p class='info'>Requête SQL:</p>";
    echo "<pre style='background:#f0f0f0;padding:10px;'>" . htmlspecialchars($sql) . "</pre>";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p class='success'>✓ Requête exécutée avec succès</p>";
    echo "<p class='info'>Nombre d'employés récupérés: " . count($employes) . "</p>";

    if (count($employes) > 0) {
        echo "<h3>Employés actifs:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Poste</th><th>Département</th><th>Statut</th></tr>";
        foreach ($employes as $emp) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($emp['id']) . "</td>";
            echo "<td>" . htmlspecialchars($emp['nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($emp['prenom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($emp['poste_nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($emp['departement_nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($emp['statut'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ Aucun employé actif trouvé dans la base</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur d'exécution: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 4. Tester avec EmployeesManager
echo "<h2>4. Test avec EmployeesManager</h2>";
try {
    require_once 'classes/EmployeesManager.php';
    $employeesManager = new EmployeesManager($conn);
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);

    echo "<p class='success'>✓ EmployeesManager instancié</p>";
    echo "<p class='info'>Nombre d'employés via EmployeesManager: " . count($employes) . "</p>";

    if (count($employes) > 0) {
        echo "<p class='success'>✓ Des employés ont été récupérés via EmployeesManager</p>";
    } else {
        echo "<p class='error'>✗ Aucun employé récupéré via EmployeesManager</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur EmployeesManager: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 5. Vérifier les données JSON pour JavaScript
echo "<h2>5. Test de l'injection JavaScript</h2>";
try {
    $employes_json = json_encode($employes, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>✓ JSON encodé avec succès</p>";
        echo "<p class='info'>Aperçu JSON:</p>";
        echo "<pre style='background:#f0f0f0;padding:10px;max-height:300px;overflow:auto;'>" .
             htmlspecialchars(substr($employes_json, 0, 500)) .
             (strlen($employes_json) > 500 ? '...' : '') .
             "</pre>";
    } else {
        echo "<p class='error'>✗ Erreur JSON: " . json_last_error_msg() . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Conclusion</h2>";
if (count($employes) > 0) {
    echo "<p class='success'><strong>✓ Les employés sont correctement récupérés</strong></p>";
    echo "<p>Le problème ne vient pas de la récupération des données. Vérifiez:</p>";
    echo "<ul>";
    echo "<li>Le JavaScript dans la page views/paie/index.php</li>";
    echo "<li>La console du navigateur pour les erreurs JavaScript</li>";
    echo "<li>Que la variable window.initialData.employes est bien définie</li>";
    echo "</ul>";
} else {
    echo "<p class='error'><strong>✗ Aucun employé n'est récupéré</strong></p>";
    echo "<p>Actions recommandées:</p>";
    echo "<ul>";
    echo "<li>Vérifier que la table 'employes' contient des enregistrements avec statut='actif'</li>";
    echo "<li>Vérifier les relations avec les tables 'postes' et 'departements'</li>";
    echo "<li>Ajouter des employés de test dans la base de données</li>";
    echo "</ul>";
}
?>
