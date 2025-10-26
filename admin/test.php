<?php
session_start();
require_once '../config.php';

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostic Employé</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .test-btn { background: #2196F3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-btn:hover { background: #0b7dda; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Système Employé</h1>";

// ====================
// 1. TEST CONNEXION BASE DE DONNÉES
// ====================
echo "<div class='section'>
    <h2>1. Test Connexion Base de Données</h2>";

try {
    $conn->query("SELECT 1");
    echo "<p class='success'>✅ Connexion à la base de données : OK</p>";
    echo "<p>Base de données : <code>" . $conn->query("SELECT DATABASE()")->fetchColumn() . "</code></p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur de connexion : " . $e->getMessage() . "</p>";
}

echo "</div>";

// ====================
// 2. STRUCTURE DE LA TABLE
// ====================
echo "<div class='section'>
    <h2>2. Structure de la table 'employes'</h2>";

try {
    $stmt = $conn->query("DESCRIBE employes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='success'>✅ Table 'employes' trouvée avec " . count($columns) . " colonnes</p>";
    echo "<table>
        <tr>
            <th>Nom Colonne</th>
            <th>Type</th>
            <th>Null</th>
            <th>Clé</th>
            <th>Défaut</th>
        </tr>";
    
    $column_names = [];
    foreach ($columns as $col) {
        echo "<tr>
            <td><strong>{$col['Field']}</strong></td>
            <td>{$col['Type']}</td>
            <td>{$col['Null']}</td>
            <td>{$col['Key']}</td>
            <td>{$col['Default']}</td>
        </tr>";
        $column_names[] = $col['Field'];
    }
    echo "</table>";
    
    // Vérifier les colonnes problématiques
    $problematic_columns = ['niveau_etude', 'langues', 'competences', 'formations', 'experiences'];
    $found_problematic = array_intersect($problematic_columns, $column_names);
    
    if (!empty($found_problematic)) {
        echo "<p class='warning'>⚠️ Colonnes obsolètes trouvées : " . implode(', ', $found_problematic) . "</p>";
    } else {
        echo "<p class='success'>✅ Aucune colonne obsolète trouvée</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur : " . $e->getMessage() . "</p>";
}

echo "</div>";

// ====================
// 3. TEST REQUÊTE INSERT
// ====================
echo "<div class='section'>
    <h2>3. Test Requête INSERT (Simulation)</h2>";

$test_data = [
    'nom' => 'TEST',
    'prenom' => 'Diagnostic',
    'email' => 'test_' . time() . '@diagnostic.com',
    'telephone' => '771234567',
    'poste_id' => 1,
    'salaire' => 150000,
    'date_embauche' => '2025-01-01',
    'heure_debut' => '08:00:00',
    'heure_fin' => '17:00:00',
    'photo' => 'default-avatar.png',
    'is_admin' => 0,
    'statut' => 'actif',
    'date_naissance' => '1990-01-01',
    'lieu_naissance' => 'Dakar',
    'nationalite' => 'Sénégalaise',
    'sexe' => 'M',
    'contact_urgence_nom' => 'Contact Test',
    'contact_urgence_relation' => 'Famille',
    'contact_urgence_telephone' => '771234567',
    'adresse' => 'Adresse test',
    'num_secu' => '123456789012345',
    'num_identite' => 'CNI123456',
    'type_identite' => 'CNI',
    'situation_familiale' => 'celibataire',
    'nombre_enfants' => 0,
    'iban' => 'SN12 3456 7890 1234 5678 90',
    'nom_banque' => 'Banque Test',
    'titulaire_compte' => 'TEST Diagnostic',
    'bic' => 'TESTSNDA',
    'cv' => null,
    'contrat' => null,
    'piece_identite' => null,
    'code_numerique' => null
];

try {
    $sql = "INSERT INTO employes (
        nom, prenom, email, telephone, poste_id, salaire, date_embauche,
        heure_debut, heure_fin, photo, is_admin, statut,
        date_naissance, lieu_naissance, nationalite, sexe,
        contact_urgence_nom, contact_urgence_relation, contact_urgence_telephone,
        adresse, num_secu, num_identite, type_identite, situation_familiale,
        nombre_enfants, iban, nom_banque, titulaire_compte, bic,
        cv, contrat, piece_identite, code_numerique
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    echo "<p><strong>Requête SQL :</strong></p>";
    echo "<code style='display:block; white-space: pre-wrap; background: #f4f4f4; padding: 10px;'>$sql</code>";
    
    $stmt = $conn->prepare($sql);
    
    $params = array_values($test_data);
    
    echo "<p><strong>Nombre de paramètres :</strong> " . count($params) . "</p>";
    echo "<p><strong>Nombre de placeholders (?) :</strong> " . substr_count($sql, '?') . "</p>";
    
    if (count($params) === substr_count($sql, '?')) {
        echo "<p class='success'>✅ Nombre de paramètres correct</p>";
        
        // Test d'exécution
        $stmt->execute($params);
        $test_id = $conn->lastInsertId();
        
        echo "<p class='success'>✅ INSERT réussi ! ID généré : $test_id</p>";
        
        // Supprimer l'enregistrement de test
        $conn->prepare("DELETE FROM employes WHERE id = ?")->execute([$test_id]);
        echo "<p class='success'>✅ Enregistrement de test supprimé</p>";
        
    } else {
        echo "<p class='error'>❌ ERREUR : Nombre de paramètres incorrect !</p>";
        echo "<p>Attendu : " . substr_count($sql, '?') . " | Reçu : " . count($params) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur SQL : " . $e->getMessage() . "</p>";
    echo "<p class='error'>Code erreur : " . $e->getCode() . "</p>";
}

echo "</div>";

// ====================
// 4. VÉRIFIER LES POSTES
// ====================
echo "<div class='section'>
    <h2>4. Vérification des Postes</h2>";

try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM postes WHERE actif = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "<p class='success'>✅ {$result['total']} poste(s) actif(s) trouvé(s)</p>";
        
        $stmt = $conn->query("SELECT id, nom, type_contrat, salaire FROM postes WHERE actif = 1 LIMIT 5");
        echo "<table>
            <tr><th>ID</th><th>Nom</th><th>Type Contrat</th><th>Salaire</th></tr>";
        while ($poste = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                <td>{$poste['id']}</td>
                <td>{$poste['nom']}</td>
                <td>{$poste['type_contrat']}</td>
                <td>" . number_format($poste['salaire'], 0, ',', ' ') . " FCFA</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Aucun poste actif trouvé ! Créez au moins un poste.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur : " . $e->getMessage() . "</p>";
}

echo "</div>";

// ====================
// 5. TEST COMPLET AVEC FORMULAIRE
// ====================
echo "<div class='section'>
    <h2>5. Test avec Formulaire Réel</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_insert'])) {
    echo "<h3>Résultat du test d'insertion :</h3>";
    
    $test_employee = [
        'nom' => $_POST['nom'] ?? 'TEST',
        'prenom' => $_POST['prenom'] ?? 'Utilisateur',
        'email' => 'test_' . time() . '@example.com',
        'telephone' => '771234567',
        'poste_id' => $_POST['poste_id'] ?? 1,
        'salaire' => 150000,
        'date_embauche' => date('Y-m-d'),
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'photo' => 'default-avatar.png',
        'is_admin' => 0,
        'statut' => 'actif'
    ];
    
    try {
        $stmt = $conn->prepare("INSERT INTO employes (nom, prenom, email, telephone, poste_id, salaire, date_embauche, heure_debut, heure_fin, photo, is_admin, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $test_employee['nom'],
            $test_employee['prenom'],
            $test_employee['email'],
            $test_employee['telephone'],
            $test_employee['poste_id'],
            $test_employee['salaire'],
            $test_employee['date_embauche'],
            $test_employee['heure_debut'],
            $test_employee['heure_fin'],
            $test_employee['photo'],
            $test_employee['is_admin'],
            $test_employee['statut']
        ]);
        
        $new_id = $conn->lastInsertId();
        echo "<p class='success'>✅ Employé test créé avec succès ! ID: $new_id</p>";
        
        // Supprimer
        $conn->prepare("DELETE FROM employes WHERE id = ?")->execute([$new_id]);
        echo "<p class='success'>✅ Employé test supprimé</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Erreur : " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>
    <h3>Test d'insertion manuel :</h3>
    <p>
        <label>Nom : <input type='text' name='nom' value='TEST' required></label><br>
        <label>Prénom : <input type='text' name='prenom' value='Diagnostic' required></label><br>
        <label>Poste ID : <input type='number' name='poste_id' value='1' required></label><br>
        <button type='submit' name='test_insert' class='test-btn'>Tester l'insertion</button>
    </p>
</form>";

echo "</div>";

// ====================
// 6. RECOMMANDATIONS
// ====================
echo "<div class='section'>
    <h2>6. Recommandations</h2>
    <ul>
        <li>Vérifiez que votre fichier <code>gestion_employe.php</code> utilise exactement les mêmes noms de colonnes que la table</li>
        <li>Assurez-vous que le nombre de paramètres dans <code>insertEmployee()</code> correspond au nombre de colonnes</li>
        <li>Vérifiez les logs d'erreur PHP dans : <code>" . ini_get('error_log') . "</code></li>
        <li>Consultez la console du navigateur (F12) pour voir les erreurs JavaScript</li>
    </ul>
</div>";

echo "<div class='section'>
    <h2>Actions Rapides</h2>
    <a href='gestion_employe.php' class='test-btn'>Retour à Gestion Employés</a>
</div>";

echo "</body></html>";
?>