<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ajout colonnes manquantes - bulletins_paie</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajout des colonnes manquantes à bulletins_paie</h1>

        <?php
        try {
            $queries = [
                "ALTER TABLE bulletins_paie ADD COLUMN montant_heures_sup DECIMAL(10,2) DEFAULT 0.00 AFTER heures_supplementaires",
                "ALTER TABLE bulletins_paie ADD COLUMN impots DECIMAL(10,2) DEFAULT 0.00 AFTER total_cotisations",
                "ALTER TABLE bulletins_paie ADD COLUMN jours_travailles TINYINT DEFAULT 0 AFTER heures_supplementaires",
                "ALTER TABLE bulletins_paie ADD COLUMN commentaires TEXT AFTER statut"
            ];

            echo "<h3>Exécution des requêtes :</h3>";
            echo "<ul>";

            foreach ($queries as $query) {
                try {
                    $conn->exec($query);
                    echo "<li class='success'>✓ " . htmlspecialchars($query) . "</li>";
                } catch (PDOException $e) {
                    // Si l'erreur est "Duplicate column name", c'est OK (colonne existe déjà)
                    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                        echo "<li class='info'>⚠ Colonne existe déjà : " . htmlspecialchars($query) . "</li>";
                    } else {
                        echo "<li class='error'>✗ Erreur : " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                }
            }

            echo "</ul>";

            // Vérifier la structure finale
            echo "<h3>Structure finale :</h3>";
            $stmt = $conn->query("DESCRIBE bulletins_paie");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<pre>";
            foreach ($columns as $col) {
                echo str_pad($col['Field'], 35) . " " . str_pad($col['Type'], 20) . " " . $col['Null'] . "\n";
            }
            echo "</pre>";

            echo "<p class='success'>✓ Colonnes ajoutées avec succès !</p>";
            echo "<p><a href='check_bulletins_structure.php'>Vérifier la structure complète</a></p>";
            echo "<p><a href='gestion_paie.php'>Retour à la gestion de paie</a></p>";

        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>
