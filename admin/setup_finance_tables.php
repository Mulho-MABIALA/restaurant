<?php
/**
 * Script de création des tables finances
 * À exécuter UNE SEULE FOIS pour installer le système financier
 */

session_start();
require_once '../config.php';

// Vérifier que c'est un admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Accès refusé. Vous devez être connecté en tant qu'administrateur.");
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Installation Tables Finances</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        h1 { color: #333; }
        .sql-output { background: white; padding: 10px; border: 1px solid #ddd; margin: 10px 0; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <h1>🛠️ Installation des Tables Système Finances</h1>";

// Lire le fichier SQL
$sqlFile = __DIR__ . '/create_finance_tables.sql';

if (!file_exists($sqlFile)) {
    echo "<div class='error'>❌ Erreur : Le fichier SQL est introuvable : $sqlFile</div>";
    exit;
}

$sql = file_get_contents($sqlFile);

// Diviser en requêtes individuelles
$queries = array_filter(
    array_map('trim',
    preg_split('/;[\s]*$/m', $sql, -1, PREG_SPLIT_NO_EMPTY))
);

echo "<div class='info'>📄 Fichier SQL chargé : " . count($queries) . " requêtes trouvées</div>";

$success_count = 0;
$error_count = 0;
$results = [];

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($queries as $index => $query) {
        // Ignorer les commentaires et lignes vides
        if (empty($query) || strpos(trim($query), '--') === 0) {
            continue;
        }

        try {
            $conn->exec($query);
            $success_count++;

            // Extraire le nom de la table si c'est un CREATE TABLE
            if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $query, $matches)) {
                $results[] = [
                    'type' => 'success',
                    'message' => "✅ Table créée : <strong>{$matches[1]}</strong>"
                ];
            } elseif (preg_match('/INSERT INTO.*?`([^`]+)`/i', $query, $matches)) {
                $results[] = [
                    'type' => 'success',
                    'message' => "✅ Données insérées dans : <strong>{$matches[1]}</strong>"
                ];
            } else {
                $results[] = [
                    'type' => 'success',
                    'message' => "✅ Requête " . ($index + 1) . " exécutée"
                ];
            }

        } catch (PDOException $e) {
            $error_count++;

            // Si la table existe déjà, ce n'est pas grave
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                $results[] = [
                    'type' => 'info',
                    'message' => "ℹ️ Table déjà existante (ignoré)"
                ];
            } else {
                $results[] = [
                    'type' => 'error',
                    'message' => "❌ Erreur : " . $e->getMessage()
                ];
            }
        }
    }

    // Afficher les résultats
    echo "<h2>📊 Résultats de l'installation</h2>";
    echo "<div class='sql-output'>";
    foreach ($results as $result) {
        $class = $result['type'];
        echo "<div class='$class'>{$result['message']}</div>";
    }
    echo "</div>";

    // Résumé
    echo "<h2>📈 Résumé</h2>";
    echo "<div class='success'>";
    echo "<strong>✅ Succès :</strong> $success_count requêtes exécutées<br>";
    if ($error_count > 0) {
        echo "<strong>⚠️ Avertissements/Erreurs :</strong> $error_count<br>";
    }
    echo "</div>";

    // Vérifier les tables créées
    echo "<h2>🗃️ Tables Finances Créées</h2>";
    $tables_check = [
        'fournisseurs',
        'factures_fournisseur',
        'factures_fournisseur_lignes',
        'paiements_fournisseur',
        'alertes_financieres'
    ];

    echo "<div class='sql-output'>";
    foreach ($tables_check as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetch();

            if ($exists) {
                // Compter les lignes
                $count_stmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<div class='success'>✅ <strong>$table</strong> - $count ligne(s)</div>";
            } else {
                echo "<div class='error'>❌ <strong>$table</strong> - Non trouvée</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='error'>❌ <strong>$table</strong> - Erreur : {$e->getMessage()}</div>";
        }
    }
    echo "</div>";

    echo "<div class='success'>";
    echo "<h3>🎉 Installation terminée !</h3>";
    echo "<p>Vous pouvez maintenant utiliser le système de gestion financière.</p>";
    echo "<p><a href='tresorerie_globale.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>→ Accéder à Trésorerie Globale</a></p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erreur critique</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
