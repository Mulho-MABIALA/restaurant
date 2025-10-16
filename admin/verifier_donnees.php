<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;

require_once '../config.php';

echo "<h1>Vérification des données</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#4CAF50;color:white;} .ok{color:green;font-weight:bold;} .error{color:red;font-weight:bold;}</style>";

// 1. Vérifier les employés
$stmt = $conn->query("SELECT COUNT(*) as total FROM employes");
$total_employes = $stmt->fetchColumn();
echo "<h2>1. Employés</h2>";
echo "<p>Total employés: <strong>$total_employes</strong></p>";

if ($total_employes > 0) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
    $actifs = $stmt->fetchColumn();
    echo "<p>Employés actifs: <strong class='".($actifs > 0 ? 'ok' : 'error')."'>$actifs</strong></p>";

    // Afficher les 5 premiers employés
    $stmt = $conn->query("SELECT id, nom, prenom, email, statut, poste_id FROM employes LIMIT 5");
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Statut</th><th>Poste ID</th></tr>";
    foreach ($employes as $emp) {
        echo "<tr>";
        echo "<td>{$emp['id']}</td>";
        echo "<td>{$emp['nom']}</td>";
        echo "<td>{$emp['prenom']}</td>";
        echo "<td>{$emp['email']}</td>";
        echo "<td>{$emp['statut']}</td>";
        echo "<td>{$emp['poste_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>Aucun employé dans la base de données !</p>";
}

// 2. Vérifier les postes
$stmt = $conn->query("SELECT COUNT(*) as total FROM postes");
$total_postes = $stmt->fetchColumn();
echo "<h2>2. Postes</h2>";
echo "<p>Total postes: <strong class='".($total_postes > 0 ? 'ok' : 'error')."'>$total_postes</strong></p>";

if ($total_postes > 0) {
    $stmt = $conn->query("SELECT id, nom, salaire_base FROM postes LIMIT 5");
    $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Nom</th><th>Salaire Base</th></tr>";
    foreach ($postes as $poste) {
        echo "<tr>";
        echo "<td>{$poste['id']}</td>";
        echo "<td>{$poste['nom']}</td>";
        echo "<td>{$poste['salaire_base']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Vérifier les départements
$stmt = $conn->query("SELECT COUNT(*) as total FROM departements");
$total_dept = $stmt->fetchColumn();
echo "<h2>3. Départements</h2>";
echo "<p>Total départements: <strong class='".($total_dept > 0 ? 'ok' : 'error')."'>$total_dept</strong></p>";

// 4. Vérifier les bulletins
$stmt = $conn->query("SELECT COUNT(*) as total FROM bulletins_paie");
$total_bulletins = $stmt->fetchColumn();
echo "<h2>4. Bulletins de paie</h2>";
echo "<p>Total bulletins: <strong>$total_bulletins</strong></p>";

// 5. Vérifier les congés
$stmt = $conn->query("SELECT COUNT(*) as total FROM conges");
$total_conges = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) as total FROM conges WHERE statut = 'en_attente'");
$conges_attente = $stmt->fetchColumn();
echo "<h2>5. Congés</h2>";
echo "<p>Total congés: <strong>$total_conges</strong></p>";
echo "<p>Congés en attente: <strong>$conges_attente</strong></p>";

// 6. Vérifier les avances
$stmt = $conn->query("SELECT COUNT(*) as total FROM avances_salaire");
$total_avances = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) as total FROM avances_salaire WHERE statut = 'en_attente'");
$avances_attente = $stmt->fetchColumn();
echo "<h2>6. Avances</h2>";
echo "<p>Total avances: <strong>$total_avances</strong></p>";
echo "<p>Avances en attente: <strong>$avances_attente</strong></p>";

// 7. Vérifier les primes
$stmt = $conn->query("SELECT COUNT(*) as total FROM primes_employes");
$total_primes = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) as total FROM primes_employes WHERE valide = 0");
$primes_attente = $stmt->fetchColumn();
echo "<h2>7. Primes</h2>";
echo "<p>Total primes: <strong>$total_primes</strong></p>";
echo "<p>Primes en attente: <strong>$primes_attente</strong></p>";

echo "<hr>";
echo "<h2>Résumé</h2>";
echo "<ul>";
if ($total_employes == 0) {
    echo "<li class='error'>❌ Vous devez ajouter des employés dans la base de données</li>";
} else {
    echo "<li class='ok'>✅ Des employés existent dans la base</li>";
}

if ($total_postes == 0) {
    echo "<li class='error'>❌ Vous devez créer des postes</li>";
} else {
    echo "<li class='ok'>✅ Des postes existent</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='gestion_paie.php' style='padding:10px 20px;background:#4CAF50;color:white;text-decoration:none;border-radius:5px;'>Retour à la gestion de paie</a></p>";
?>
