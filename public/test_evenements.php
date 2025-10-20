<?php
require_once '../config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Test de la table événements</h1>";

    // Test 1 : Vérifier si la table existe
    echo "<h2>1. Vérification de l'existence de la table</h2>";
    try {
        $stmt = $conn->query("DESCRIBE evenements");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p style='color:green'>✅ La table 'evenements' existe</p>";
        echo "<h3>Colonnes de la table :</h3><pre>";
        print_r($columns);
        echo "</pre>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Erreur : " . $e->getMessage() . "</p>";
        die();
    }

    // Test 2 : Compter les événements
    echo "<h2>2. Nombre d'événements</h2>";
    $stmt = $conn->query("SELECT COUNT(*) as total FROM evenements");
    $count = $stmt->fetch();
    echo "<p>Nombre total d'événements : <strong>{$count['total']}</strong></p>";

    // Test 3 : Afficher tous les événements
    echo "<h2>3. Liste des événements</h2>";
    $stmt = $conn->query("SELECT * FROM evenements");
    $evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($evenements)) {
        echo "<p style='color:orange'>⚠️ Aucun événement dans la base de données</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Titre</th><th>Date</th><th>Heure</th><th>Lieu</th><th>Image</th></tr>";
        foreach ($evenements as $event) {
            echo "<tr>";
            echo "<td>{$event['id']}</td>";
            echo "<td>{$event['titre']}</td>";
            echo "<td>{$event['date_evenement']}</td>";
            echo "<td>{$event['heure_evenement']}</td>";
            echo "<td>{$event['lieu']}</td>";
            echo "<td>" . ($event['image'] ?? 'Aucune') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test 4 : Vérifier la table galerie
    echo "<h2>4. Vérification de la table evenements_galerie</h2>";
    try {
        $stmt = $conn->query("DESCRIBE evenements_galerie");
        echo "<p style='color:green'>✅ La table 'evenements_galerie' existe</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠️ La table 'evenements_galerie' n'existe pas encore : " . $e->getMessage() . "</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erreur générale : " . $e->getMessage() . "</p>";
}
?>
