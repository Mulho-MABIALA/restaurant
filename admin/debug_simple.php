<?php
// Créez ce fichier: test_employes.php
// Pour tester rapidement la correction

require_once '../config.php';

try {
    // Test avec la correction
    $stmt = $conn->prepare("
        SELECT 
            e.id as id_employe, 
            e.nom, 
            e.prenom, 
            e.email,
            e.telephone,
            e.salaire_base,
            e.date_embauche,
            e.statut
        FROM employes e
        WHERE e.statut = 'actif'
        ORDER BY e.nom, e.prenom
        LIMIT 5
    ");
    
    $stmt->execute();
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>✅ Test réussi !</h2>";
    echo "<p>Nombre d'employés actifs trouvés: <strong>" . count($employes) . "</strong></p>";
    
    if (count($employes) > 0) {
        echo "<h3>Exemples d'employés :</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Salaire</th></tr>";
        
        foreach ($employes as $emp) {
            echo "<tr>";
            echo "<td>" . $emp['id_employe'] . "</td>";
            echo "<td>" . htmlspecialchars($emp['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($emp['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($emp['email']) . "</td>";
            echo "<td>" . number_format($emp['salaire_base'], 0, ',', ' ') . " FCFA</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>JSON pour l'API :</h3>";
        echo "<textarea rows='10' cols='80'>";
        echo json_encode([
            'success' => true,
            'employes' => $employes,
            'count' => count($employes)
        ], JSON_PRETTY_PRINT);
        echo "</textarea>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Erreur :</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>