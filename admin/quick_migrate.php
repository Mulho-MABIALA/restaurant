<?php
/**
 * Migration rapide - Ajout des colonnes horaires
 * Exécutez ce fichier une seule fois
 */

require_once '../config.php';

try {
    // Vérifier si les colonnes existent déjà
    $stmt = $conn->query("SHOW COLUMNS FROM categories LIKE 'heure_debut'");

    if ($stmt->rowCount() > 0) {
        die("✅ Les colonnes existent déjà! Aucune action nécessaire.");
    }

    // Ajouter les colonnes
    $conn->exec("
        ALTER TABLE categories
        ADD COLUMN heure_debut TIME DEFAULT NULL,
        ADD COLUMN heure_fin TIME DEFAULT NULL,
        ADD COLUMN disponibilite_active TINYINT(1) DEFAULT 0
    ");

    echo "✅ SUCCÈS! Les colonnes ont été ajoutées à la table categories.<br><br>";
    echo "Vous pouvez maintenant retourner à: <a href='categories_plats.php'>Catégories de Plats</a>";

} catch (PDOException $e) {
    echo "❌ ERREUR: " . $e->getMessage();
    echo "<br><br>Essayez d'exécuter ce SQL manuellement dans phpMyAdmin:<br><br>";
    echo "<pre>";
    echo "ALTER TABLE categories\n";
    echo "ADD COLUMN heure_debut TIME DEFAULT NULL,\n";
    echo "ADD COLUMN heure_fin TIME DEFAULT NULL,\n";
    echo "ADD COLUMN disponibilite_active TINYINT(1) DEFAULT 0;";
    echo "</pre>";
}
?>
