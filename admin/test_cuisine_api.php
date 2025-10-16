<?php
/**
 * API de test pour le système cuisine
 */

session_start();
require_once '../config.php';

// Simuler connexion admin
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
}

header('Content-Type: application/json');

$test = $_GET['test'] ?? '';

switch ($test) {
    case 'database':
        testDatabase($conn);
        break;

    case 'tables':
        testTables($conn);
        break;

    case 'create_order':
        createTestOrder($conn);
        break;

    case 'delete_test_orders':
        deleteTestOrders($conn);
        break;

    case 'files':
        testFiles();
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Test non spécifié'
        ]);
}

function testDatabase($conn) {
    try {
        $stmt = $conn->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Connexion à la base de données réussie',
            'database' => $result['db_name']
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function testTables($conn) {
    $tables = ['commandes', 'commande_details', 'notifications', 'plats'];
    $results = [];

    try {
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $results[] = [
                'name' => $table,
                'exists' => $exists
            ];
        }

        // Vérifier les colonnes importantes de commandes
        $stmt = $conn->query("SHOW COLUMNS FROM commandes");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $important_columns = ['statut', 'vu_admin', 'type_commande', 'created_at'];
        foreach ($important_columns as $col) {
            $exists = in_array($col, $columns);
            $results[] = [
                'name' => "commandes.$col",
                'exists' => $exists
            ];
        }

        echo json_encode([
            'success' => true,
            'tables' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function createTestOrder($conn) {
    try {
        // Créer une commande test
        $stmt = $conn->prepare("
            INSERT INTO commandes (
                nom_client, email, telephone, num_table,
                total, statut, statut_paiement, vu_admin,
                type_commande, created_at, date_commande
            ) VALUES (
                'TEST CLIENT', 'test@example.com', '0600000000', '99',
                15000, 'En cours', 'Impayé', 0,
                NULL, NOW(), NOW()
            )
        ");

        $stmt->execute();
        $commande_id = $conn->lastInsertId();

        // Ajouter des détails
        $stmt = $conn->prepare("
            INSERT INTO commande_details (commande_id, nom_plat, quantite, prix)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$commande_id, 'Test Burger', 2, 5000]);
        $stmt->execute([$commande_id, 'Test Frites', 1, 2500]);
        $stmt->execute([$commande_id, 'Test Coca', 2, 1250]);

        echo json_encode([
            'success' => true,
            'message' => 'Commande test créée',
            'commande_id' => $commande_id,
            'statut' => 'En cours'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function deleteTestOrders($conn) {
    try {
        // Supprimer les détails
        $stmt = $conn->query("
            DELETE cd FROM commande_details cd
            INNER JOIN commandes c ON cd.commande_id = c.id
            WHERE c.nom_client = 'TEST CLIENT'
        ");

        // Supprimer les commandes
        $stmt = $conn->prepare("DELETE FROM commandes WHERE nom_client = 'TEST CLIENT'");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => 'Commandes test supprimées'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function testFiles() {
    $base = __DIR__;
    $files = [
        'cuisine.php' => $base . '/cuisine.php',
        'api_cuisine_notifications.php' => $base . '/api_cuisine_notifications.php',
        'js/cuisine_notifications.js' => $base . '/js/cuisine_notifications.js',
        'README_CUISINE.md' => $base . '/README_CUISINE.md',
        'INTEGRATION_NOTIFICATIONS.md' => $base . '/INTEGRATION_NOTIFICATIONS.md',
        'sql/cuisine_setup.sql' => $base . '/sql/cuisine_setup.sql'
    ];

    $results = [];
    foreach ($files as $name => $path) {
        $results[] = [
            'name' => $name,
            'exists' => file_exists($path),
            'path' => $path
        ];
    }

    echo json_encode([
        'success' => true,
        'files' => $results
    ]);
}
?>
