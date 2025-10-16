<?php
/**
 * Page de test du système de cuisine
 * Permet de vérifier que tous les composants fonctionnent correctement
 */

session_start();
require_once '../config.php';

// Simuler une connexion admin pour les tests
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'Test Admin';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Système Cuisine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .test-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-result {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
        }
        .test-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .test-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .btn-test {
            margin: 5px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .status-ok {
            background: #28a745;
            color: white;
        }
        .status-error {
            background: #dc3545;
            color: white;
        }
        .status-warning {
            background: #ffc107;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-flask"></i> Test du Système Cuisine</h1>

        <!-- Test 1 : Connexion BDD -->
        <div class="test-card">
            <h3><i class="fas fa-database"></i> Test 1 : Connexion Base de Données</h3>
            <button class="btn btn-primary btn-test" onclick="testDatabase()">
                <i class="fas fa-play"></i> Tester
            </button>
            <div id="test-database-result"></div>
        </div>

        <!-- Test 2 : Tables existantes -->
        <div class="test-card">
            <h3><i class="fas fa-table"></i> Test 2 : Structure des Tables</h3>
            <button class="btn btn-primary btn-test" onclick="testTables()">
                <i class="fas fa-play"></i> Tester
            </button>
            <div id="test-tables-result"></div>
        </div>

        <!-- Test 3 : API Cuisine -->
        <div class="test-card">
            <h3><i class="fas fa-plug"></i> Test 3 : API Cuisine</h3>
            <button class="btn btn-primary btn-test" onclick="testAPI()">
                <i class="fas fa-play"></i> Tester
            </button>
            <div id="test-api-result"></div>
        </div>

        <!-- Test 4 : Créer une commande test -->
        <div class="test-card">
            <h3><i class="fas fa-plus"></i> Test 4 : Créer Commande Test</h3>
            <button class="btn btn-success btn-test" onclick="createTestOrder()">
                <i class="fas fa-plus"></i> Créer Commande
            </button>
            <button class="btn btn-danger btn-test" onclick="deleteTestOrders()">
                <i class="fas fa-trash"></i> Supprimer Tests
            </button>
            <div id="test-order-result"></div>
        </div>

        <!-- Test 5 : Notifications -->
        <div class="test-card">
            <h3><i class="fas fa-bell"></i> Test 5 : Système de Notifications</h3>
            <button class="btn btn-primary btn-test" onclick="testNotifications()">
                <i class="fas fa-play"></i> Tester
            </button>
            <div id="test-notifications-result"></div>
        </div>

        <!-- Test 6 : Fichiers -->
        <div class="test-card">
            <h3><i class="fas fa-file"></i> Test 6 : Fichiers Système</h3>
            <button class="btn btn-primary btn-test" onclick="testFiles()">
                <i class="fas fa-play"></i> Tester
            </button>
            <div id="test-files-result"></div>
        </div>

        <!-- Actions rapides -->
        <div class="test-card">
            <h3><i class="fas fa-tools"></i> Actions Rapides</h3>
            <div class="btn-group" role="group">
                <a href="cuisine.php" class="btn btn-info" target="_blank">
                    <i class="fas fa-utensils"></i> Ouvrir Cuisine
                </a>
                <a href="commandes.php" class="btn btn-info" target="_blank">
                    <i class="fas fa-receipt"></i> Ouvrir Commandes
                </a>
                <button class="btn btn-warning" onclick="runAllTests()">
                    <i class="fas fa-play-circle"></i> Tous les tests
                </button>
            </div>
        </div>
    </div>

    <script>
        // Test 1 : Base de données
        async function testDatabase() {
            const result = document.getElementById('test-database-result');
            result.innerHTML = '<div class="test-info">Test en cours...</div>';

            try {
                const response = await fetch('test_cuisine_api.php?test=database');
                const data = await response.json();

                if (data.success) {
                    result.innerHTML = `
                        <div class="test-success">
                            <i class="fas fa-check-circle"></i> ${data.message}
                            <br>Nom BDD: ${data.database}
                        </div>
                    `;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Test 2 : Tables
        async function testTables() {
            const result = document.getElementById('test-tables-result');
            result.innerHTML = '<div class="test-info">Test en cours...</div>';

            try {
                const response = await fetch('test_cuisine_api.php?test=tables');
                const data = await response.json();

                if (data.success) {
                    let html = '<div class="test-success"><i class="fas fa-check-circle"></i> Tables vérifiées</div>';
                    html += '<div class="test-info">';
                    data.tables.forEach(table => {
                        const badge = table.exists ? 'status-ok' : 'status-error';
                        const icon = table.exists ? 'check' : 'times';
                        html += `<span class="status-badge ${badge}"><i class="fas fa-${icon}"></i> ${table.name}</span> `;
                    });
                    html += '</div>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Test 3 : API
        async function testAPI() {
            const result = document.getElementById('test-api-result');
            result.innerHTML = '<div class="test-info">Test en cours...</div>';

            try {
                // Test count
                const response1 = await fetch('api_cuisine_notifications.php?action=count_commandes_pretes');
                const data1 = await response1.json();

                // Test get_commandes
                const response2 = await fetch('api_cuisine_notifications.php?action=get_commandes_pretes');
                const data2 = await response2.json();

                if (data1.success && data2.success) {
                    result.innerHTML = `
                        <div class="test-success">
                            <i class="fas fa-check-circle"></i> API fonctionnelle
                            <br>Commandes prêtes: ${data1.count}
                            <br>Commandes récupérées: ${data2.commandes.length}
                        </div>
                    `;
                } else {
                    result.innerHTML = '<div class="test-error"><i class="fas fa-times-circle"></i> API ne répond pas correctement</div>';
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Test 4 : Créer commande
        async function createTestOrder() {
            const result = document.getElementById('test-order-result');
            result.innerHTML = '<div class="test-info">Création en cours...</div>';

            try {
                const response = await fetch('test_cuisine_api.php?test=create_order');
                const data = await response.json();

                if (data.success) {
                    result.innerHTML = `
                        <div class="test-success">
                            <i class="fas fa-check-circle"></i> Commande créée !
                            <br>ID: ${data.commande_id}
                            <br>Statut: ${data.statut}
                        </div>
                    `;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Supprimer commandes test
        async function deleteTestOrders() {
            if (!confirm('Supprimer toutes les commandes de test ?')) return;

            const result = document.getElementById('test-order-result');
            result.innerHTML = '<div class="test-info">Suppression en cours...</div>';

            try {
                const response = await fetch('test_cuisine_api.php?test=delete_test_orders');
                const data = await response.json();

                if (data.success) {
                    result.innerHTML = `
                        <div class="test-success">
                            <i class="fas fa-check-circle"></i> ${data.deleted} commandes supprimées
                        </div>
                    `;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Test 5 : Notifications
        async function testNotifications() {
            const result = document.getElementById('test-notifications-result');
            result.innerHTML = '<div class="test-info">Test en cours...</div>';

            try {
                const response = await fetch('api_cuisine_notifications.php?action=get_notifications');
                const data = await response.json();

                if (data.success) {
                    result.innerHTML = `
                        <div class="test-success">
                            <i class="fas fa-check-circle"></i> Système de notifications OK
                            <br>Notifications non lues: ${data.count}
                        </div>
                    `;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Test 6 : Fichiers
        async function testFiles() {
            const result = document.getElementById('test-files-result');
            result.innerHTML = '<div class="test-info">Test en cours...</div>';

            try {
                const response = await fetch('test_cuisine_api.php?test=files');
                const data = await response.json();

                if (data.success) {
                    let html = '<div class="test-success"><i class="fas fa-check-circle"></i> Fichiers vérifiés</div>';
                    html += '<div class="test-info">';
                    data.files.forEach(file => {
                        const badge = file.exists ? 'status-ok' : 'status-error';
                        const icon = file.exists ? 'check' : 'times';
                        html += `<div><span class="status-badge ${badge}"><i class="fas fa-${icon}"></i></span> ${file.name}</div>`;
                    });
                    html += '</div>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                }
            } catch (error) {
                result.innerHTML = `<div class="test-error"><i class="fas fa-times-circle"></i> Erreur: ${error.message}</div>`;
            }
        }

        // Tous les tests
        async function runAllTests() {
            await testDatabase();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testTables();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testAPI();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testNotifications();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testFiles();
        }
    </script>
</body>
</html>
