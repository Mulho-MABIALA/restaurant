<?php
session_start();
require_once '../config.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Système RH - Restaurant</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="gradient-bg text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-tools text-4xl"></i>
                        <div>
                            <h1 class="text-3xl font-bold">Configuration du Système RH</h1>
                            <p class="text-sm opacity-90">Assistant de mise en place</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold"><?= htmlspecialchars($admin_name) ?></p>
                        <p class="text-sm opacity-75"><?= date('d/m/Y H:i') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Bannière d'avertissement -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-8 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-yellow-800">Première utilisation du système RH</h3>
                        <p class="mt-2 text-sm text-yellow-700">
                            Si vous rencontrez l'erreur "La table 'type_primes' n'existe pas" ou une autre table manquante,
                            suivez les étapes ci-dessous pour configurer complètement votre système RH.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Guide étape par étape -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-list-ol text-purple-600 mr-3"></i>
                    Guide de configuration en 3 étapes
                </h2>

                <!-- Étape 1 -->
                <div class="mb-8">
                    <div class="flex items-start">
                        <div class="step-number bg-green-100 text-green-600">1</div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">
                                Créer les tables de la base de données
                                <span class="status-badge bg-red-100 text-red-800">Obligatoire</span>
                            </h3>
                            <p class="text-gray-600 mb-4">
                                Créez automatiquement toutes les 11 tables nécessaires au fonctionnement du système RH
                                (départements, postes, employés, horaires, présences, primes, congés, avances, bulletins).
                            </p>
                            <a href="create_rh_tables.php"
                               class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-database mr-2"></i>
                                Créer les tables RH
                            </a>
                            <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                    Ce script vérifie les tables existantes et crée uniquement celles qui manquent.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Étape 2 -->
                <div class="mb-8 border-t pt-8">
                    <div class="flex items-start">
                        <div class="step-number bg-blue-100 text-blue-600">2</div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">
                                Initialiser les données de test
                                <span class="status-badge bg-yellow-100 text-yellow-800">Recommandé</span>
                            </h3>
                            <p class="text-gray-600 mb-4">
                                Ajoutez des données de démonstration : 4 départements, 5 postes, 6 employés de test,
                                et 6 types de primes prédéfinis. Idéal pour découvrir le système.
                            </p>
                            <a href="init_test_data.php"
                               class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-users mr-2"></i>
                                Créer les données de test
                            </a>
                            <div class="mt-4 bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-700 font-medium mb-2">Données créées :</p>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li><i class="fas fa-check text-blue-500 mr-2"></i>4 départements (Cuisine, Service, Bar, Gestion)</li>
                                    <li><i class="fas fa-check text-blue-500 mr-2"></i>5 postes avec salaires (de 200k à 500k FCFA)</li>
                                    <li><i class="fas fa-check text-blue-500 mr-2"></i>6 employés actifs avec profils complets</li>
                                    <li><i class="fas fa-check text-blue-500 mr-2"></i>6 types de primes configurés</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Étape 3 -->
                <div class="border-t pt-8">
                    <div class="flex items-start">
                        <div class="step-number bg-purple-100 text-purple-600">3</div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">
                                Accéder au système RH
                                <span class="status-badge bg-green-100 text-green-800">Prêt !</span>
                            </h3>
                            <p class="text-gray-600 mb-4">
                                Une fois les tables créées et les données initialisées, vous pouvez accéder au système complet
                                de gestion des ressources humaines.
                            </p>
                            <a href="gestion_paie.php"
                               class="inline-flex items-center px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg shadow-lg transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-rocket mr-2"></i>
                                Ouvrir le système RH
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outils de diagnostic -->
            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <!-- Diagnostic employés -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-indigo-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 ml-4">Diagnostic Employés</h3>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Vérifiez l'état de la base de données, les tables et la récupération des employés.
                    </p>
                    <a href="diagnostic_employes.php"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>
                        Employés
                    </a>
                </div>

                <!-- Diagnostic présences -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-green-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 ml-4">Diagnostic Présences</h3>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Diagnostiquer les problèmes "Employé non trouvé" dans l'onglet Présences.
                    </p>
                    <a href="diagnostic_presences.php"
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-stethoscope mr-2"></i>
                        Présences
                    </a>
                </div>

                <!-- Documentation -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-orange-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 ml-4">Documentation</h3>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Consultez le guide complet de résolution des problèmes et la documentation technique
                        du système RH.
                    </p>
                    <div class="flex gap-2">
                        <a href="SOLUTION_EMPLOYES.md" target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-file-alt mr-2"></i>
                            Guide rapide
                        </a>
                        <a href="README_GESTION_PAIE.md" target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-book-open mr-2"></i>
                            Guide complet
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informations système -->
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Informations système
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Base de données</p>
                        <p class="font-semibold text-gray-800">
                            <?php
                            try {
                                $dbName = $conn->query("SELECT DATABASE()")->fetchColumn();
                                echo htmlspecialchars($dbName);
                            } catch (Exception $e) {
                                echo "Non connecté";
                            }
                            ?>
                        </p>
                    </div>
                    <div class="bg-white p-4 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Version PHP</p>
                        <p class="font-semibold text-gray-800"><?= PHP_VERSION ?></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">PDO MySQL</p>
                        <p class="font-semibold text-gray-800">
                            <?= extension_loaded('pdo_mysql') ? '✅ Activé' : '❌ Désactivé' ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-gray-500 text-sm">
                <p>
                    <i class="fas fa-shield-alt mr-1"></i>
                    Assurez-vous d'être connecté en tant qu'administrateur pour exécuter ces scripts
                </p>
                <p class="mt-2">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700 font-medium">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Retour au tableau de bord
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
