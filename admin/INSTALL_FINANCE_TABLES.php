<?php
/**
 * INSTALLATION RAPIDE DES TABLES FINANCES
 * Exécutez ce fichier dans votre navigateur : http://localhost/restaurant/admin/INSTALL_FINANCE_TABLES.php
 */

require_once '../config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Installation Tables Finances</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .btn { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #218838; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .progress { margin: 20px 0; }
        .step { padding: 10px; margin: 5px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Installation Tables Système Finances</h1>";

$tables_created = 0;
$errors = [];

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='progress'>";

    // 1. Table fournisseurs
    echo "<div class='step'>";
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `fournisseurs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nom` VARCHAR(255) NOT NULL,
            `contact_nom` VARCHAR(255) DEFAULT NULL,
            `telephone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `adresse` TEXT DEFAULT NULL,
            `ville` VARCHAR(100) DEFAULT NULL,
            `code_postal` VARCHAR(10) DEFAULT NULL,
            `pays` VARCHAR(100) DEFAULT 'Sénégal',
            `siret` VARCHAR(14) DEFAULT NULL,
            `tva_numero` VARCHAR(20) DEFAULT NULL,
            `conditions_paiement` INT(11) DEFAULT 30,
            `mode_paiement` VARCHAR(50) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `actif` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<div class='success'>✅ Table <strong>fournisseurs</strong> créée avec succès</div>";
        $tables_created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ Table <strong>fournisseurs</strong> existe déjà</div>";
        } else {
            echo "<div class='error'>❌ Erreur fournisseurs: " . $e->getMessage() . "</div>";
            $errors[] = $e->getMessage();
        }
    }
    echo "</div>";

    // 2. Table factures_fournisseur
    echo "<div class='step'>";
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `factures_fournisseur` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `fournisseur_id` INT(11) DEFAULT NULL,
            `numero_facture` VARCHAR(100) NOT NULL,
            `date_facture` DATE NOT NULL,
            `date_echeance` DATE DEFAULT NULL,
            `montant_ht` DECIMAL(10,2) DEFAULT 0.00,
            `taux_tva` DECIMAL(5,2) DEFAULT 0.00,
            `montant_tva` DECIMAL(10,2) DEFAULT 0.00,
            `montant_ttc` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `montant_paye` DECIMAL(10,2) DEFAULT 0.00,
            `montant_restant` DECIMAL(10,2) DEFAULT 0.00,
            `statut` ENUM('brouillon', 'en_attente', 'payee_partiellement', 'payee') DEFAULT 'en_attente',
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fournisseur_id` (`fournisseur_id`),
            KEY `statut` (`statut`),
            KEY `date_facture` (`date_facture`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<div class='success'>✅ Table <strong>factures_fournisseur</strong> créée avec succès</div>";
        $tables_created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ Table <strong>factures_fournisseur</strong> existe déjà</div>";
        } else {
            echo "<div class='error'>❌ Erreur factures_fournisseur: " . $e->getMessage() . "</div>";
            $errors[] = $e->getMessage();
        }
    }
    echo "</div>";

    // 3. Table factures_fournisseur_lignes
    echo "<div class='step'>";
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `factures_fournisseur_lignes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `facture_id` INT(11) NOT NULL,
            `designation` VARCHAR(255) NOT NULL,
            `quantite` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            `prix_unitaire` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `montant_ligne` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `facture_id` (`facture_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<div class='success'>✅ Table <strong>factures_fournisseur_lignes</strong> créée avec succès</div>";
        $tables_created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ Table <strong>factures_fournisseur_lignes</strong> existe déjà</div>";
        } else {
            echo "<div class='error'>❌ Erreur factures_fournisseur_lignes: " . $e->getMessage() . "</div>";
            $errors[] = $e->getMessage();
        }
    }
    echo "</div>";

    // 4. Table paiements_fournisseur
    echo "<div class='step'>";
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `paiements_fournisseur` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `facture_id` INT(11) NOT NULL,
            `date_paiement` DATE NOT NULL,
            `montant` DECIMAL(10,2) NOT NULL,
            `mode_paiement` ENUM('Espèces', 'Chèque', 'Virement', 'Carte bancaire', 'Mobile Money') NOT NULL,
            `reference` VARCHAR(100) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `facture_id` (`facture_id`),
            KEY `date_paiement` (`date_paiement`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<div class='success'>✅ Table <strong>paiements_fournisseur</strong> créée avec succès</div>";
        $tables_created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ Table <strong>paiements_fournisseur</strong> existe déjà</div>";
        } else {
            echo "<div class='error'>❌ Erreur paiements_fournisseur: " . $e->getMessage() . "</div>";
            $errors[] = $e->getMessage();
        }
    }
    echo "</div>";

    // 5. Table alertes_financieres
    echo "<div class='step'>";
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `alertes_financieres` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `type_alerte` VARCHAR(50) NOT NULL,
            `priorite` ENUM('critical', 'warning', 'info') DEFAULT 'info',
            `titre` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `statut` ENUM('active', 'lue', 'resolue') DEFAULT 'active',
            `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `date_traitement` TIMESTAMP NULL DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `type_alerte` (`type_alerte`),
            KEY `priorite` (`priorite`),
            KEY `statut` (`statut`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<div class='success'>✅ Table <strong>alertes_financieres</strong> créée avec succès</div>";
        $tables_created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ Table <strong>alertes_financieres</strong> existe déjà</div>";
        } else {
            echo "<div class='error'>❌ Erreur alertes_financieres: " . $e->getMessage() . "</div>";
            $errors[] = $e->getMessage();
        }
    }
    echo "</div>";

    echo "</div>"; // fin progress

    // Résumé final
    echo "<hr style='margin: 30px 0;'>";

    if (empty($errors)) {
        echo "<div class='success'>";
        echo "<h2 style='margin: 0 0 10px 0;'>🎉 Installation réussie !</h2>";
        echo "<p><strong>$tables_created</strong> tables ont été créées ou vérifiées.</p>";
        echo "<p>Le système de gestion financière est maintenant opérationnel.</p>";
        echo "<a href='tresorerie_globale.php' class='btn'>→ Accéder à Trésorerie Globale</a>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<h2>⚠️ Installation terminée avec des avertissements</h2>";
        echo "<p>Certaines erreurs ont été rencontrées :</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    // Vérification finale
    echo "<h3 style='margin-top: 30px;'>📊 Vérification des tables</h3>";
    $tables_to_check = ['fournisseurs', 'factures_fournisseur', 'factures_fournisseur_lignes', 'paiements_fournisseur', 'alertes_financieres'];

    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Table</th>";
    echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Statut</th>";
    echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Lignes</th>";
    echo "</tr>";

    foreach ($tables_to_check as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetch();

            if ($exists) {
                $count_stmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<tr>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>$table</strong></td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center; color: green;'>✅ Existe</td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>$count</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>$table</strong></td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center; color: red;'>❌ Manquante</td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>-</td>";
                echo "</tr>";
            }
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>$table</strong></td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center; color: orange;'>⚠️ Erreur</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>-</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erreur critique de connexion</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "
    </div>
</body>
</html>";
?>
