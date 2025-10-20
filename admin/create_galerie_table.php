<?php
require_once '../config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Créer la table pour la galerie d'événements
    $sql = "CREATE TABLE IF NOT EXISTS evenements_galerie (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evenement_id INT NOT NULL,
        image VARCHAR(255) NOT NULL,
        legende VARCHAR(255),
        ordre INT DEFAULT 0,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
        INDEX idx_evenement (evenement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->exec($sql);

    echo "✅ Table 'evenements_galerie' créée avec succès !<br>";
    echo "<br><a href='admin_evenements.php'>← Retour à la gestion des événements</a>";
    echo "<br><a href='../public/evenements.php' target='_blank'>→ Voir la page publique</a>";

} catch(PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
