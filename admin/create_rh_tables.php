<?php
session_start();
require_once '../config.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Accès refusé. Veuillez vous connecter en tant qu'administrateur.");
}

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>";
echo "<title>Création des tables RH</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; }
h2 { color: #4f46e5; margin-top: 30px; }
.success { color: #10b981; padding: 10px; background: #d1fae5; border-left: 4px solid #10b981; margin: 10px 0; }
.error { color: #ef4444; padding: 10px; background: #fee2e2; border-left: 4px solid #ef4444; margin: 10px 0; }
.info { color: #3b82f6; padding: 10px; background: #dbeafe; border-left: 4px solid #3b82f6; margin: 10px 0; }
.warning { color: #f59e0b; padding: 10px; background: #fef3c7; border-left: 4px solid #f59e0b; margin: 10px 0; }
.sql-preview { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 12px; margin: 10px 0; }
.btn { display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
.btn:hover { background: #4338ca; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
table th { background: #f9fafb; font-weight: 600; }
</style></head><body><div class='container'>";

echo "<h1>🗄️ Création des tables du Système RH</h1>";

try {
    $conn->beginTransaction();

    $tablesCreated = 0;
    $tablesExisting = 0;
    $errors = [];

    // Définition de toutes les tables nécessaires
    $tables = [
        'departements' => "
            CREATE TABLE IF NOT EXISTS `departements` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `couleur` varchar(7) DEFAULT '#3b82f6',
                `description` text,
                `actif` tinyint(1) DEFAULT 1,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'postes' => "
            CREATE TABLE IF NOT EXISTS `postes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `departement_id` int(11) DEFAULT NULL,
                `salaire` decimal(10,2) DEFAULT NULL,
                `couleur` varchar(7) DEFAULT '#3b82f6',
                `type_contrat` enum('CDI','CDD','Stage','Interim') DEFAULT 'CDI',
                `duree_contrat` int(11) DEFAULT NULL,
                `niveau_hierarchique` int(11) DEFAULT 1,
                `competences_requises` text,
                `avantages` text,
                `code_paie` varchar(50) DEFAULT NULL,
                `categorie_paie` varchar(50) DEFAULT NULL,
                `regime_social` varchar(50) DEFAULT 'general',
                `taux_cotisation` decimal(5,2) DEFAULT 0.00,
                `salaire_min` decimal(10,2) DEFAULT NULL,
                `salaire_max` decimal(10,2) DEFAULT NULL,
                `heures_travail` int(11) DEFAULT 173,
                `heures_semaine` int(11) DEFAULT 35,
                `heures_mois` int(11) DEFAULT 151,
                `poste_superieur_id` int(11) DEFAULT NULL,
                `actif` tinyint(1) DEFAULT 1,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_departement` (`departement_id`),
                KEY `fk_poste_superieur` (`poste_superieur_id`),
                CONSTRAINT `fk_postes_departement` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_poste_superieur` FOREIGN KEY (`poste_superieur_id`) REFERENCES `postes` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'employes' => "
            CREATE TABLE IF NOT EXISTS `employes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `prenom` varchar(100) NOT NULL,
                `email` varchar(150) DEFAULT NULL,
                `telephone` varchar(20) DEFAULT NULL,
                `poste_id` int(11) DEFAULT NULL,
                `departement_id` int(11) DEFAULT NULL,
                `salaire` decimal(10,2) DEFAULT NULL,
                `statut` enum('actif','inactif','conge','suspendu') DEFAULT 'actif',
                `date_embauche` date DEFAULT NULL,
                `date_fin_contrat` date DEFAULT NULL,
                `heure_debut` time DEFAULT '08:00:00',
                `heure_fin` time DEFAULT '17:00:00',
                `photo` varchar(255) DEFAULT NULL,
                `adresse` text,
                `ville` varchar(100) DEFAULT NULL,
                `code_postal` varchar(10) DEFAULT NULL,
                `pays` varchar(100) DEFAULT 'Sénégal',
                `date_naissance` date DEFAULT NULL,
                `lieu_naissance` varchar(100) DEFAULT NULL,
                `numero_secu` varchar(50) DEFAULT NULL,
                `numero_cnss` varchar(50) DEFAULT NULL,
                `situation_familiale` enum('celibataire','marie','divorce','veuf') DEFAULT 'celibataire',
                `nombre_enfants` int(11) DEFAULT 0,
                `contact_urgence` varchar(100) DEFAULT NULL,
                `tel_urgence` varchar(20) DEFAULT NULL,
                `is_admin` tinyint(1) DEFAULT 0,
                `notes` text,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                KEY `fk_employes_poste` (`poste_id`),
                KEY `fk_employes_departement` (`departement_id`),
                KEY `idx_statut` (`statut`),
                CONSTRAINT `fk_employes_poste` FOREIGN KEY (`poste_id`) REFERENCES `postes` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_employes_departement` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'horaires' => "
            CREATE TABLE IF NOT EXISTS `horaires` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employe_id` int(11) NOT NULL,
                `semaine_debut` date NOT NULL,
                `lundi_debut` time DEFAULT NULL,
                `lundi_fin` time DEFAULT NULL,
                `mardi_debut` time DEFAULT NULL,
                `mardi_fin` time DEFAULT NULL,
                `mercredi_debut` time DEFAULT NULL,
                `mercredi_fin` time DEFAULT NULL,
                `jeudi_debut` time DEFAULT NULL,
                `jeudi_fin` time DEFAULT NULL,
                `vendredi_debut` time DEFAULT NULL,
                `vendredi_fin` time DEFAULT NULL,
                `samedi_debut` time DEFAULT NULL,
                `samedi_fin` time DEFAULT NULL,
                `dimanche_debut` time DEFAULT NULL,
                `dimanche_fin` time DEFAULT NULL,
                `notes` text,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_horaires_employe` (`employe_id`),
                KEY `idx_semaine` (`semaine_debut`),
                CONSTRAINT `fk_horaires_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'presences' => "
            CREATE TABLE IF NOT EXISTS `presences` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employe_id` int(11) NOT NULL,
                `heure_arrivee` datetime DEFAULT NULL,
                `heure_depart` datetime DEFAULT NULL,
                `commentaire` text,
                `valide_par` int(11) DEFAULT NULL,
                `date_validation` datetime DEFAULT NULL,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_presences_employe` (`employe_id`),
                KEY `idx_date_arrivee` (`heure_arrivee`),
                CONSTRAINT `fk_presences_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'type_primes' => "
            CREATE TABLE IF NOT EXISTS `type_primes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `description` text,
                `montant_fixe` decimal(10,2) DEFAULT NULL,
                `pourcentage_salaire` decimal(5,2) DEFAULT NULL,
                `recurrent` tinyint(1) DEFAULT 0,
                `imposable` tinyint(1) DEFAULT 1,
                `actif` tinyint(1) DEFAULT 1,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'primes_employes' => "
            CREATE TABLE IF NOT EXISTS `primes_employes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_employe` int(11) NOT NULL,
                `id_type_prime` int(11) NOT NULL,
                `montant` decimal(10,2) NOT NULL,
                `mois` int(11) NOT NULL,
                `annee` int(11) NOT NULL,
                `motif` text,
                `valide` tinyint(1) DEFAULT 0,
                `valide_par` int(11) DEFAULT NULL,
                `date_validation` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_primes_employe` (`id_employe`),
                KEY `fk_primes_type` (`id_type_prime`),
                KEY `idx_periode` (`mois`, `annee`),
                CONSTRAINT `fk_primes_employe` FOREIGN KEY (`id_employe`) REFERENCES `employes` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_primes_type` FOREIGN KEY (`id_type_prime`) REFERENCES `type_primes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'conges' => "
            CREATE TABLE IF NOT EXISTS `conges` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employe_id` int(11) NOT NULL,
                `type_conge` enum('conge_paye','maladie','sans_solde','maternite','paternite','autre') DEFAULT 'conge_paye',
                `date_debut` date NOT NULL,
                `date_fin` date NOT NULL,
                `nombre_jours` int(11) NOT NULL,
                `motif` text,
                `statut` enum('en_attente','approuve','refuse','annule') DEFAULT 'en_attente',
                `approuve_par` int(11) DEFAULT NULL,
                `date_approbation` datetime DEFAULT NULL,
                `commentaire_admin` text,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_conges_employe` (`employe_id`),
                KEY `idx_dates` (`date_debut`, `date_fin`),
                KEY `idx_statut` (`statut`),
                CONSTRAINT `fk_conges_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'soldes_conges' => "
            CREATE TABLE IF NOT EXISTS `soldes_conges` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employe_id` int(11) NOT NULL,
                `annee` int(11) NOT NULL,
                `jours_acquis` decimal(5,2) DEFAULT 0.00,
                `jours_pris` decimal(5,2) DEFAULT 0.00,
                `jours_restants` decimal(5,2) DEFAULT 0.00,
                `jours_report` decimal(5,2) DEFAULT 0.00,
                `date_maj` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_employe_annee` (`employe_id`, `annee`),
                CONSTRAINT `fk_soldes_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'avances_salaire' => "
            CREATE TABLE IF NOT EXISTS `avances_salaire` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_employe` int(11) NOT NULL,
                `montant` decimal(10,2) NOT NULL,
                `motif` text,
                `statut` enum('en_attente','approuve','refuse','rembourse') DEFAULT 'en_attente',
                `date_demande` datetime DEFAULT CURRENT_TIMESTAMP,
                `date_approbation` datetime DEFAULT NULL,
                `approuve_par` int(11) DEFAULT NULL,
                `date_remboursement` date DEFAULT NULL,
                `nombre_mensualites` int(11) DEFAULT 1,
                `mensualites_restantes` int(11) DEFAULT NULL,
                `commentaire` text,
                PRIMARY KEY (`id`),
                KEY `fk_avances_employe` (`id_employe`),
                KEY `idx_statut` (`statut`),
                CONSTRAINT `fk_avances_employe` FOREIGN KEY (`id_employe`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'bulletins_paie' => "
            CREATE TABLE IF NOT EXISTS `bulletins_paie` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `employe_id` int(11) NOT NULL,
                `mois` int(11) NOT NULL,
                `annee` int(11) NOT NULL,
                `salaire_base` decimal(10,2) NOT NULL,
                `heures_travaillees` decimal(10,2) DEFAULT 0.00,
                `heures_supplementaires` decimal(10,2) DEFAULT 0.00,
                `montant_heures_sup` decimal(10,2) DEFAULT 0.00,
                `primes` decimal(10,2) DEFAULT 0.00,
                `avances` decimal(10,2) DEFAULT 0.00,
                `retenues` decimal(10,2) DEFAULT 0.00,
                `cotisations_sociales` decimal(10,2) DEFAULT 0.00,
                `impots` decimal(10,2) DEFAULT 0.00,
                `salaire_brut` decimal(10,2) NOT NULL,
                `salaire_net` decimal(10,2) NOT NULL,
                `jours_travailles` int(11) DEFAULT 0,
                `jours_absences` int(11) DEFAULT 0,
                `jours_conges` int(11) DEFAULT 0,
                `statut` enum('brouillon','valide','paye') DEFAULT 'brouillon',
                `date_paiement` date DEFAULT NULL,
                `mode_paiement` enum('virement','especes','cheque') DEFAULT 'virement',
                `commentaires` text,
                `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
                `date_validation` datetime DEFAULT NULL,
                `valide_par` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_employe_periode` (`employe_id`, `mois`, `annee`),
                KEY `idx_periode` (`mois`, `annee`),
                KEY `idx_statut` (`statut`),
                CONSTRAINT `fk_bulletins_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    echo "<h2>📊 État des tables</h2>";
    echo "<table>";
    echo "<thead><tr><th>Table</th><th>Statut</th><th>Action</th></tr></thead>";
    echo "<tbody>";

    foreach ($tables as $tableName => $createSQL) {
        try {
            // Vérifier si la table existe
            $stmt = $conn->query("SHOW TABLES LIKE '$tableName'");
            $exists = $stmt->rowCount() > 0;

            if ($exists) {
                $count = $conn->query("SELECT COUNT(*) FROM `$tableName`")->fetchColumn();
                echo "<tr>";
                echo "<td><strong>$tableName</strong></td>";
                echo "<td><span class='info'>✓ Existe déjà ($count enregistrement(s))</span></td>";
                echo "<td>-</td>";
                echo "</tr>";
                $tablesExisting++;
            } else {
                // Créer la table
                $conn->exec($createSQL);
                echo "<tr>";
                echo "<td><strong>$tableName</strong></td>";
                echo "<td><span class='success'>✓ Créée avec succès</span></td>";
                echo "<td>Nouvelle table</td>";
                echo "</tr>";
                $tablesCreated++;
            }
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td><strong>$tableName</strong></td>";
            echo "<td><span class='error'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</span></td>";
            echo "<td>Échec</td>";
            echo "</tr>";
            $errors[] = "Table $tableName: " . $e->getMessage();
        }
    }

    echo "</tbody></table>";

    // Créer les types de primes par défaut si la table est vide
    $stmt = $conn->query("SELECT COUNT(*) FROM type_primes");
    if ($stmt->fetchColumn() == 0) {
        echo "<h2>🎁 Création des types de primes par défaut</h2>";

        $typesPrimes = [
            ['Prime de performance', 'Prime basée sur les performances mensuelles', NULL, 10, 1, 1],
            ['Prime de présence', 'Prime pour assiduité sans absence', 50000, NULL, 1, 1],
            ['Prime exceptionnelle', 'Prime ponctuelle pour effort exceptionnel', NULL, NULL, 0, 1],
            ['Prime de fin d\'année', '13ème mois', NULL, 100, 0, 1],
            ['Prime de transport', 'Indemnité de transport', 25000, NULL, 1, 0],
            ['Prime de restauration', 'Indemnité repas', 30000, NULL, 1, 0],
        ];

        $stmt = $conn->prepare("
            INSERT INTO type_primes (nom, description, montant_fixe, pourcentage_salaire, recurrent, imposable)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($typesPrimes as $prime) {
            $stmt->execute($prime);
            echo "<div class='success'>✓ Type de prime '{$prime[0]}' créé</div>";
        }
    }

    $conn->commit();

    // Résumé
    echo "<h2>📝 Résumé</h2>";
    echo "<div class='info'>";
    echo "<p><strong>Tables créées:</strong> $tablesCreated</p>";
    echo "<p><strong>Tables existantes:</strong> $tablesExisting</p>";
    echo "<p><strong>Total:</strong> " . ($tablesCreated + $tablesExisting) . " tables</p>";
    echo "</div>";

    if (count($errors) > 0) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Avertissements</h3>";
        foreach ($errors as $error) {
            echo "<p>• " . htmlspecialchars($error) . "</p>";
        }
        echo "</div>";
    }

    if ($tablesCreated > 0 || count($errors) == 0) {
        echo "<div class='success'>";
        echo "<h3>✅ Succès !</h3>";
        echo "<p>Le système RH est prêt à être utilisé.</p>";
        echo "</div>";
    }

    echo "<h2>🚀 Prochaines étapes</h2>";
    echo "<div class='info'>";
    echo "<ol>";
    echo "<li><a href='diagnostic_employes.php' class='btn'>1. Lancer le diagnostic</a></li>";
    echo "<li><a href='init_test_data.php' class='btn'>2. Initialiser les données de test</a></li>";
    echo "<li><a href='gestion_paie.php' class='btn'>3. Accéder au système RH</a></li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<div class='error'>";
    echo "<h3>❌ Erreur critique</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . " (ligne " . $e->getLine() . ")</p>";
    echo "<details><summary>Trace complète</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
    echo "</div>";
}

echo "</div></body></html>";
?>
