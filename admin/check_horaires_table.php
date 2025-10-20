<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Structure table horaires</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        table { background: white; border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Structure de la table horaires</h1>

    <?php
    try {
        $stmt = $conn->query("DESCRIBE horaires");
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

        // Vérifier si les colonnes de pause existent
        $existingColumns = array_column($columns, 'Field');
        $pauseColumns = [];
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        foreach ($jours as $jour) {
            $pauseColumns[] = $jour . '_pause_debut';
            $pauseColumns[] = $jour . '_pause_fin';
        }

        echo "<h2>Colonnes de pause manquantes</h2>";
        $missing = [];
        foreach ($pauseColumns as $col) {
            if (!in_array($col, $existingColumns)) {
                $missing[] = $col;
                echo "<p class='error'>✗ $col</p>";
            } else {
                echo "<p class='success'>✓ $col</p>";
            }
        }

        if (!empty($missing)) {
            echo "<h3>Script SQL pour ajouter les colonnes manquantes :</h3>";
            echo "<pre>";
            foreach ($jours as $jour) {
                echo "ALTER TABLE horaires ADD COLUMN {$jour}_pause_debut TIME NULL AFTER {$jour}_fin;\n";
                echo "ALTER TABLE horaires ADD COLUMN {$jour}_pause_fin TIME NULL AFTER {$jour}_pause_debut;\n";
            }
            echo "</pre>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>
