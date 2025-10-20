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

echo "<h1>Initialisation des données de test - Système RH</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

try {
    $conn->beginTransaction();

    // 1. Créer des départements si nécessaire
    echo "<h2>1. Création des départements</h2>";

    $stmt = $conn->query("SELECT COUNT(*) FROM departements");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $departements = [
            ['nom' => 'Cuisine', 'couleur' => '#ef4444', 'description' => 'Service de préparation culinaire'],
            ['nom' => 'Service', 'couleur' => '#3b82f6', 'description' => 'Service en salle'],
            ['nom' => 'Bar', 'couleur' => '#f59e0b', 'description' => 'Service bar et boissons'],
            ['nom' => 'Gestion', 'couleur' => '#8b5cf6', 'description' => 'Direction et gestion'],
        ];

        $stmt = $conn->prepare("
            INSERT INTO departements (nom, couleur, description, actif, date_creation)
            VALUES (?, ?, ?, 1, NOW())
        ");

        foreach ($departements as $dept) {
            $stmt->execute([$dept['nom'], $dept['couleur'], $dept['description']]);
            echo "<p class='success'>✓ Département '{$dept['nom']}' créé</p>";
        }
    } else {
        echo "<p class='info'>→ $count département(s) déjà existant(s)</p>";
    }

    // 2. Créer des postes si nécessaire
    echo "<h2>2. Création des postes</h2>";

    $stmt = $conn->query("SELECT COUNT(*) FROM postes");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Récupérer les IDs des départements
        $depts = $conn->query("SELECT id, nom FROM departements")->fetchAll(PDO::FETCH_KEY_PAIR);

        $postes = [
            [
                'nom' => 'Chef Cuisinier',
                'departement_id' => $depts['Cuisine'] ?? 1,
                'salaire' => 450000,
                'couleur' => '#dc2626',
                'type_contrat' => 'CDI',
                'heures_travail' => 173
            ],
            [
                'nom' => 'Cuisinier',
                'departement_id' => $depts['Cuisine'] ?? 1,
                'salaire' => 300000,
                'couleur' => '#ef4444',
                'type_contrat' => 'CDI',
                'heures_travail' => 173
            ],
            [
                'nom' => 'Serveur',
                'departement_id' => $depts['Service'] ?? 2,
                'salaire' => 200000,
                'couleur' => '#3b82f6',
                'type_contrat' => 'CDI',
                'heures_travail' => 173
            ],
            [
                'nom' => 'Barman',
                'departement_id' => $depts['Bar'] ?? 3,
                'salaire' => 250000,
                'couleur' => '#f59e0b',
                'type_contrat' => 'CDI',
                'heures_travail' => 173
            ],
            [
                'nom' => 'Manager',
                'departement_id' => $depts['Gestion'] ?? 4,
                'salaire' => 500000,
                'couleur' => '#8b5cf6',
                'type_contrat' => 'CDI',
                'heures_travail' => 173
            ],
        ];

        $stmt = $conn->prepare("
            INSERT INTO postes (
                nom, departement_id, salaire, couleur, type_contrat,
                heures_travail, actif, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        foreach ($postes as $poste) {
            $stmt->execute([
                $poste['nom'],
                $poste['departement_id'],
                $poste['salaire'],
                $poste['couleur'],
                $poste['type_contrat'],
                $poste['heures_travail']
            ]);
            echo "<p class='success'>✓ Poste '{$poste['nom']}' créé</p>";
        }
    } else {
        echo "<p class='info'>→ $count poste(s) déjà existant(s)</p>";
    }

    // 3. Créer des employés de test si nécessaire
    echo "<h2>3. Création des employés de test</h2>";

    $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut = 'actif'");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Récupérer les IDs des postes
        $postes_ids = $conn->query("SELECT id, nom FROM postes")->fetchAll(PDO::FETCH_KEY_PAIR);

        $employes = [
            [
                'nom' => 'DIOP',
                'prenom' => 'Mamadou',
                'email' => 'mamadou.diop@restaurant.sn',
                'telephone' => '771234567',
                'poste_id' => $postes_ids['Chef Cuisinier'] ?? 1,
                'salaire' => 450000,
                'statut' => 'actif'
            ],
            [
                'nom' => 'NDIAYE',
                'prenom' => 'Fatou',
                'email' => 'fatou.ndiaye@restaurant.sn',
                'telephone' => '772345678',
                'poste_id' => $postes_ids['Serveur'] ?? 3,
                'salaire' => 200000,
                'statut' => 'actif'
            ],
            [
                'nom' => 'SALL',
                'prenom' => 'Ibrahima',
                'email' => 'ibrahima.sall@restaurant.sn',
                'telephone' => '773456789',
                'poste_id' => $postes_ids['Cuisinier'] ?? 2,
                'salaire' => 300000,
                'statut' => 'actif'
            ],
            [
                'nom' => 'FALL',
                'prenom' => 'Aminata',
                'email' => 'aminata.fall@restaurant.sn',
                'telephone' => '774567890',
                'poste_id' => $postes_ids['Serveur'] ?? 3,
                'salaire' => 200000,
                'statut' => 'actif'
            ],
            [
                'nom' => 'KANE',
                'prenom' => 'Ousmane',
                'email' => 'ousmane.kane@restaurant.sn',
                'telephone' => '775678901',
                'poste_id' => $postes_ids['Barman'] ?? 4,
                'salaire' => 250000,
                'statut' => 'actif'
            ],
            [
                'nom' => 'SARR',
                'prenom' => 'Aissatou',
                'email' => 'aissatou.sarr@restaurant.sn',
                'telephone' => '776789012',
                'poste_id' => $postes_ids['Manager'] ?? 5,
                'salaire' => 500000,
                'statut' => 'actif'
            ],
        ];

        $stmt = $conn->prepare("
            INSERT INTO employes (
                nom, prenom, email, telephone, poste_id, salaire,
                statut, date_embauche, heure_debut, heure_fin
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), '08:00:00', '17:00:00')
        ");

        foreach ($employes as $emp) {
            $stmt->execute([
                $emp['nom'],
                $emp['prenom'],
                $emp['email'],
                $emp['telephone'],
                $emp['poste_id'],
                $emp['salaire'],
                $emp['statut']
            ]);
            echo "<p class='success'>✓ Employé '{$emp['prenom']} {$emp['nom']}' créé</p>";
        }
    } else {
        echo "<p class='info'>→ $count employé(s) actif(s) déjà existant(s)</p>";
    }

    $conn->commit();

    echo "<hr>";
    echo "<h2>✓ Initialisation terminée avec succès!</h2>";
    echo "<p class='success'>Les données de test ont été créées.</p>";
    echo "<p><a href='diagnostic_employes.php'>→ Vérifier les données</a></p>";
    echo "<p><a href='gestion_paie.php'>→ Accéder à la gestion de la paie</a></p>";

} catch (Exception $e) {
    $conn->rollBack();
    echo "<p class='error'>✗ Erreur: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
