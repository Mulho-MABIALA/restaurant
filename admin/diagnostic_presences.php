<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Accès refusé");
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Diagnostic Présences</title>";
echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
h1 { color: #4f46e5; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; }
h2 { color: #3b82f6; margin-top: 30px; }
.success { color: #10b981; padding: 10px; background: #d1fae5; border-left: 4px solid #10b981; margin: 10px 0; }
.error { color: #ef4444; padding: 10px; background: #fee2e2; border-left: 4px solid #ef4444; margin: 10px 0; }
.info { color: #3b82f6; padding: 10px; background: #dbeafe; border-left: 4px solid #3b82f6; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
table th, table td { padding: 12px; text-align: left; border: 1px solid #e5e7eb; }
table th { background: #f9fafb; font-weight: 600; }
code { background: #1e293b; color: #e2e8f0; padding: 2px 6px; border-radius: 3px; }
.sql-query { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; margin: 10px 0; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Diagnostic - Problème des présences</h1>";

try {
    // 1. Vérifier la structure de la table employes
    echo "<h2>1. Structure de la table employes</h2>";
    $stmt = $conn->query("DESCRIBE employes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasDepartementId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'departement_id') {
            $hasDepartementId = true;
            break;
        }
    }

    if ($hasDepartementId) {
        echo "<div class='success'>✓ La colonne 'departement_id' existe dans la table employes</div>";
    } else {
        echo "<div class='info'>ℹ️ La colonne 'departement_id' n'existe PAS dans la table employes</div>";
        echo "<div class='info'>→ Le département doit être récupéré via la table postes</div>";
    }

    echo "<table>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Lister tous les employés actifs avec leurs relations
    echo "<h2>2. Liste des employés actifs et leurs relations</h2>";

    $sql = "
        SELECT
            e.id,
            e.nom,
            e.prenom,
            e.email,
            e.poste_id,
            p.nom as poste_nom,
            p.departement_id as dept_id_via_poste,
            d.nom as departement_nom,
            p.heures_semaine,
            p.heures_mois,
            p.heures_travail
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE e.statut = 'actif'
        ORDER BY e.id
    ";

    echo "<div class='sql-query'>" . htmlspecialchars($sql) . "</div>";

    $stmt = $conn->query($sql);
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Nombre d'employés actifs trouvés: " . count($employes) . "</div>";

    if (count($employes) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th><th>Nom</th><th>Prénom</th><th>Poste</th><th>Poste ID</th>";
        echo "<th>Dépt (via poste)</th><th>Heures/semaine</th><th>Heures/mois</th>";
        echo "</tr>";

        foreach ($employes as $emp) {
            $hasProblems = empty($emp['poste_id']) || empty($emp['poste_nom']);
            $rowClass = $hasProblems ? "style='background:#fee2e2;'" : "";

            echo "<tr $rowClass>";
            echo "<td><strong>" . htmlspecialchars($emp['id']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($emp['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($emp['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($emp['poste_nom'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>" . htmlspecialchars($emp['poste_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($emp['departement_nom'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($emp['heures_semaine'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($emp['heures_mois'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Compter les problèmes
        $employesSansPoste = array_filter($employes, fn($e) => empty($e['poste_id']));
        if (count($employesSansPoste) > 0) {
            echo "<div class='error'>";
            echo "⚠️ <strong>" . count($employesSansPoste) . " employé(s) n'ont pas de poste assigné</strong>";
            echo "<p>Ces employés causeront l'erreur 'Employé non trouvé' car les données de poste sont manquantes.</p>";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>❌ Aucun employé actif trouvé</div>";
    }

    // 3. Tester la requête exacte utilisée par get_details_presence_employe
    echo "<h2>3. Test de la requête get_details_presence_employe</h2>";

    if (count($employes) > 0) {
        $testEmployeId = $employes[0]['id'];
        echo "<div class='info'>Test avec l'employé ID: $testEmployeId ({$employes[0]['prenom']} {$employes[0]['nom']})</div>";

        $sql = "
            SELECT e.*, p.nom as poste_nom, d.nom as departement_nom,
                   p.heures_semaine, p.heures_mois, p.heures_travail as heures_par_mois
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE e.id = ?
        ";

        echo "<div class='sql-query'>" . htmlspecialchars($sql) . "</div>";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$testEmployeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo "<div class='success'>✓ Requête réussie - Employé trouvé</div>";
            echo "<table>";
            echo "<tr><th>Champ</th><th>Valeur</th></tr>";
            foreach ($result as $key => $value) {
                if (in_array($key, ['id', 'nom', 'prenom', 'poste_nom', 'departement_nom', 'heures_semaine', 'heures_mois'])) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Employé non trouvé avec cette requête</div>";
        }
    }

    // 4. Vérifier les présences
    echo "<h2>4. Vérification des présences</h2>";

    $stmt = $conn->query("
        SELECT
            p.id,
            p.employe_id,
            e.nom,
            e.prenom,
            p.heure_arrivee,
            p.heure_depart,
            DATE(p.heure_arrivee) as date_presence
        FROM presences p
        LEFT JOIN employes e ON p.employe_id = e.id
        ORDER BY p.heure_arrivee DESC
        LIMIT 20
    ");
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Dernières 20 présences enregistrées: " . count($presences) . "</div>";

    if (count($presences) > 0) {
        echo "<table>";
        echo "<tr><th>ID Présence</th><th>Employé ID</th><th>Nom</th><th>Date</th><th>Arrivée</th><th>Départ</th></tr>";
        foreach ($presences as $pres) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($pres['id']) . "</td>";
            echo "<td>" . htmlspecialchars($pres['employe_id']) . "</td>";
            echo "<td>" . htmlspecialchars($pres['nom'] . ' ' . $pres['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($pres['date_presence']) . "</td>";
            echo "<td>" . htmlspecialchars($pres['heure_arrivee']) . "</td>";
            echo "<td>" . htmlspecialchars($pres['heure_depart'] ?? 'En cours') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Aucune présence enregistrée</div>";
    }

    // 5. Diagnostic des problèmes potentiels
    echo "<h2>5. Diagnostic des problèmes</h2>";

    $problemes = [];

    // Vérifier les employés sans poste
    $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut = 'actif' AND (poste_id IS NULL OR poste_id = 0)");
    $employesSansPoste = $stmt->fetchColumn();
    if ($employesSansPoste > 0) {
        $problemes[] = "<strong>$employesSansPoste employé(s) actif(s) sans poste assigné</strong> - Ils ne pourront pas afficher leurs détails";
    }

    // Vérifier les postes sans département
    $stmt = $conn->query("SELECT COUNT(*) FROM postes WHERE actif = 1 AND (departement_id IS NULL OR departement_id = 0)");
    $postesSansDept = $stmt->fetchColumn();
    if ($postesSansDept > 0) {
        $problemes[] = "<strong>$postesSansDept poste(s) sans département assigné</strong> - Le département n'apparaîtra pas";
    }

    if (count($problemes) > 0) {
        echo "<div class='error'>";
        echo "<h3>❌ Problèmes détectés:</h3>";
        echo "<ul>";
        foreach ($problemes as $prob) {
            echo "<li>$prob</li>";
        }
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='success'>✓ Aucun problème structurel détecté</div>";
    }

    // 6. Solutions recommandées
    echo "<h2>6. Solutions recommandées</h2>";

    if ($employesSansPoste > 0) {
        echo "<div class='error'>";
        echo "<h3>Action requise: Assigner des postes aux employés</h3>";
        echo "<p>Exécutez cette requête pour voir quels employés n'ont pas de poste:</p>";
        echo "<div class='sql-query'>SELECT id, nom, prenom, email FROM employes WHERE statut = 'actif' AND (poste_id IS NULL OR poste_id = 0)</div>";
        echo "<p>Ensuite, assignez-leur un poste via l'interface d'administration ou avec une requête UPDATE.</p>";
        echo "</div>";
    }

    if ($hasDepartementId) {
        echo "<div class='info'>";
        echo "<h3>ℹ️ Information: Colonne departement_id détectée</h3>";
        echo "<p>Votre table employes contient une colonne 'departement_id'. Pour éviter toute confusion:</p>";
        echo "<ul>";
        echo "<li>Soit vous utilisez <code>e.departement_id</code> pour lier directement l'employé au département</li>";
        echo "<li>Soit vous utilisez <code>p.departement_id</code> pour passer par le poste (recommandé)</li>";
        echo "</ul>";
        echo "<p>Actuellement, le code corrigé utilise <code>p.departement_id</code> (via le poste).</p>";
        echo "</div>";
    }

    echo "<div class='success'>";
    echo "<h3>✅ Correction appliquée</h3>";
    echo "<p>Le fichier <code>gestion_paie.php</code> a été corrigé pour utiliser:</p>";
    echo "<div class='sql-query'>LEFT JOIN departements d ON p.departement_id = d.id</div>";
    echo "<p>au lieu de:</p>";
    echo "<div class='sql-query' style='background:#fee2e2;color:#991b1b;'>LEFT JOIN departements d ON e.departement_id = d.id</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erreur</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>Actions suivantes</h2>";
echo "<p><a href='gestion_paie.php' style='display:inline-block;padding:10px 20px;background:#4f46e5;color:white;text-decoration:none;border-radius:5px;'>← Retour à la gestion RH</a></p>";
echo "<p><a href='rh_setup.php' style='display:inline-block;padding:10px 20px;background:#10b981;color:white;text-decoration:none;border-radius:5px;margin-left:10px;'>🔧 Configuration RH</a></p>";

echo "</div></body></html>";
?>
