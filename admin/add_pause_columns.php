<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ajout des colonnes de pause</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        ul { list-style: none; padding: 0; }
        li { padding: 8px; margin: 5px 0; background: #f9f9f9; border-left: 4px solid #4CAF50; }
        li.error { border-left-color: #f44336; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajout des colonnes de pause dans la table horaires</h1>

        <?php
        try {
            $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

            echo "<h3>Exécution des requêtes :</h3>";
            echo "<ul>";

            foreach ($jours as $jour) {
                try {
                    // Ajouter colonne pause_debut
                    $sql1 = "ALTER TABLE horaires ADD COLUMN {$jour}_pause_debut TIME NULL AFTER {$jour}_fin";
                    $conn->exec($sql1);
                    echo "<li class='success'>✓ Colonne {$jour}_pause_debut ajoutée</li>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                        echo "<li class='info'>⚠ {$jour}_pause_debut existe déjà</li>";
                    } else {
                        echo "<li class='error'>✗ Erreur {$jour}_pause_debut: " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                }

                try {
                    // Ajouter colonne pause_fin
                    $sql2 = "ALTER TABLE horaires ADD COLUMN {$jour}_pause_fin TIME NULL AFTER {$jour}_pause_debut";
                    $conn->exec($sql2);
                    echo "<li class='success'>✓ Colonne {$jour}_pause_fin ajoutée</li>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                        echo "<li class='info'>⚠ {$jour}_pause_fin existe déjà</li>";
                    } else {
                        echo "<li class='error'>✗ Erreur {$jour}_pause_fin: " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                }
            }

            echo "</ul>";

            // Vérifier la structure finale
            echo "<h3>Vérification de la structure finale :</h3>";
            $stmt = $conn->query("DESCRIBE horaires");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pauseColumns = [];
            foreach ($jours as $jour) {
                $pauseColumns[] = $jour . '_pause_debut';
                $pauseColumns[] = $jour . '_pause_fin';
            }

            $existingColumns = array_column($columns, 'Field');
            $allPresent = true;

            echo "<ul>";
            foreach ($pauseColumns as $col) {
                if (in_array($col, $existingColumns)) {
                    echo "<li class='success'>✓ $col</li>";
                } else {
                    echo "<li class='error'>✗ $col MANQUANTE</li>";
                    $allPresent = false;
                }
            }
            echo "</ul>";

            if ($allPresent) {
                echo "<p class='success' style='font-size: 18px; margin-top: 20px;'>✅ Toutes les colonnes de pause ont été ajoutées avec succès !</p>";
                echo "<p><a href='planification_horaires.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Aller à la planification des horaires</a></p>";
            } else {
                echo "<p class='error'>❌ Certaines colonnes n'ont pas pu être ajoutées. Vérifiez les erreurs ci-dessus.</p>";
            }

        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>
