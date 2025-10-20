<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Structure de la table conges</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        table { background: white; border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>Structure de la table conges</h1>

    <?php
    try {
        $stmt = $conn->query("DESCRIBE conges");
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

    } catch (Exception $e) {
        echo "<p style='color:red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>
