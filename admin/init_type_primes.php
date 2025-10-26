<?php
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Initialiser les types de primes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Initialisation des types de primes</h1>

        <?php
        try {
            // Vérifier les types de primes existants
            $stmt = $conn->query("SELECT * FROM type_primes");
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h2>Types de primes existants</h2>";
            if (empty($existing)) {
                echo "<p class='error'>Aucun type de prime trouvé. Création des types par défaut...</p>";
            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Nom</th><th>Description</th><th>Montant par défaut</th></tr>";
                foreach ($existing as $type) {
                    echo "<tr>";
                    echo "<td>{$type['id']}</td>";
                    echo "<td>{$type['nom']}</td>";
                    echo "<td>{$type['description']}</td>";
                    echo "<td>" . ($type['montant_defaut'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            // Créer les types par défaut si aucun n'existe
            if (empty($existing)) {
                $typesPrimes = [
                    ['Performance', 'Prime de performance exceptionnelle', 50000],
                    ['Présence', 'Prime de présence et assiduité', 25000],
                    ['Productivité', 'Prime de productivité', 40000],
                    ['Ancienneté', 'Prime d\'ancienneté', 30000],
                    ['Objectifs', 'Prime sur atteinte des objectifs', 60000],
                    ['Responsabilité', 'Prime de responsabilité', 35000],
                    ['Exceptionnelle', 'Prime exceptionnelle ponctuelle', 100000],
                    ['Fin d\'année', 'Prime de fin d\'année', 75000]
                ];

                $stmt = $conn->prepare("
                    INSERT INTO type_primes (nom, description, montant_defaut)
                    VALUES (?, ?, ?)
                ");

                echo "<h2>Création des types de primes par défaut</h2>";
                echo "<ul>";
                foreach ($typesPrimes as $type) {
                    $stmt->execute($type);
                    echo "<li class='success'>✓ {$type[0]} - {$type[1]} (Montant par défaut: " . number_format($type[2], 0, ',', ' ') . " FCFA)</li>";
                }
                echo "</ul>";

                echo "<p class='success'>✓ " . count($typesPrimes) . " types de primes créés avec succès !</p>";
            }

            // Afficher le résultat final
            $stmt = $conn->query("SELECT * FROM type_primes ORDER BY id");
            $final = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h2>Types de primes disponibles</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Nom</th><th>Description</th><th>Montant par défaut</th></tr>";
            foreach ($final as $type) {
                echo "<tr>";
                echo "<td>{$type['id']}</td>";
                echo "<td><strong>{$type['nom']}</strong></td>";
                echo "<td>{$type['description']}</td>";
                echo "<td>" . number_format($type['montant_defaut'] ?? 0, 0, ',', ' ') . " FCFA</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<p><a href='gestion_paie.php'>Retour à la gestion de paie</a></p>";

        } catch (Exception $e) {
            echo "<p class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>
