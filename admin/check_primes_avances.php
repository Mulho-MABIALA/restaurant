<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vérification structure tables primes et avances</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
        table { background: white; border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Vérification des tables primes_employes et avances_salaire</h1>

    <div class="container">
        <h2>Table: primes_employes</h2>
        <?php
        try {
            $stmt = $conn->query("DESCRIBE primes_employes");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="container">
        <h2>Table: avances_salaire</h2>
        <?php
        try {
            $stmt = $conn->query("DESCRIBE avances_salaire");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="container">
        <h2>Test des requêtes SUM(montant)</h2>
        <?php
        try {
            // Test primes
            echo "<h3>Test SELECT SUM(montant) FROM primes_employes</h3>";
            $stmt = $conn->query("SELECT SUM(montant) as total FROM primes_employes LIMIT 1");
            $result = $stmt->fetch();
            echo "<p>Résultat: " . ($result['total'] ?? 'NULL') . "</p>";

            // Test avances
            echo "<h3>Test SELECT SUM(montant) FROM avances_salaire</h3>";
            $stmt = $conn->query("SELECT SUM(montant) as total FROM avances_salaire LIMIT 1");
            $result = $stmt->fetch();
            echo "<p>Résultat: " . ($result['total'] ?? 'NULL') . "</p>";

        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>
