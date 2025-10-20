<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Structure de bulletins_paie</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        table { background: white; border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Structure de la table bulletins_paie</h1>

    <?php
    try {
        $stmt = $conn->query("DESCRIBE bulletins_paie");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<h2>Vérification des colonnes requises</h2>";
        $requiredColumns = [
            'employe_id', 'mois', 'annee',
            'salaire_base', 'heures_travaillees', 'heures_supplementaires', 'montant_heures_sup',
            'primes', 'avances', 'retenues',
            'cotisations_sociales', 'impots',
            'salaire_brut', 'salaire_net',
            'jours_travailles', 'jours_absences', 'jours_conges',
            'statut', 'commentaires', 'date_creation'
        ];

        $existingColumns = array_column($columns, 'Field');

        echo "<ul>";
        foreach ($requiredColumns as $reqCol) {
            if (in_array($reqCol, $existingColumns)) {
                echo "<li class='success'>✓ $reqCol</li>";
            } else {
                echo "<li class='error'>✗ $reqCol MANQUANTE!</li>";
            }
        }
        echo "</ul>";

    } catch (Exception $e) {
        echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>
