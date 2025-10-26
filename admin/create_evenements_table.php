<?php
require_once '../config.php';

try {
    // Créer la table evenements
    $sql = "CREATE TABLE IF NOT EXISTS evenements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        date_evenement DATE NOT NULL,
        heure_debut TIME,
        heure_fin TIME,
        lieu VARCHAR(255),
        prix DECIMAL(10, 2) DEFAULT 0,
        image_principale VARCHAR(255),
        actif TINYINT(1) DEFAULT 1,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->exec($sql);

    // Créer la table pour la galerie d'événements
    $sql2 = "CREATE TABLE IF NOT EXISTS evenements_galerie (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evenement_id INT NOT NULL,
        image VARCHAR(255) NOT NULL,
        legende VARCHAR(255),
        ordre INT DEFAULT 0,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->exec($sql2);

    echo "✅ Tables créées avec succès !<br>";
    echo "- Table 'evenements' créée<br>";
    echo "- Table 'evenements_galerie' créée<br>";
    echo "<br><a href='admin_evenements.php'>Aller à la gestion des événements</a>";

} catch(PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
