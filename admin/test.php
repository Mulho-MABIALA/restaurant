<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test de diagnostic complet
$diagnostics = [];

// 1. Test de la session
session_start();
$diagnostics['session'] = [
    'status' => session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive',
    'id' => session_id(),
    'admin_logged_in' => isset($_SESSION['admin_logged_in']) ? '✅ Oui' : '❌ Non',
    'admin_id' => $_SESSION['admin_id'] ?? 'Non défini',
    'admin_name' => $_SESSION['admin_name'] ?? 'Non défini',
];

// 2. Test de connexion à la base de données
try {
    require_once '../config.php';
    $diagnostics['database'] = [
        'status' => '✅ Connexion réussie',
        'driver' => $conn->getAttribute(PDO::ATTR_DRIVER_NAME),
    ];
    
    // Test de requête
    $test_query = $conn->query("SELECT COUNT(*) FROM reservations");
    $diagnostics['database']['test_query'] = '✅ Requête OK';
    
} catch (Exception $e) {
    $diagnostics['database'] = [
        'status' => '❌ Erreur',
        'message' => $e->getMessage()
    ];
}

// 3. Test des fichiers requis
$required_files = [
    '../config.php' => file_exists('../config.php'),
    './permissions.php' => file_exists('./permissions.php'),
    './sidebar.php' => file_exists('./sidebar.php'),
];

$diagnostics['files'] = [];
foreach ($required_files as $file => $exists) {
    $diagnostics['files'][$file] = $exists ? '✅ Existe' : '❌ Manquant';
}

// 4. Test des permissions PHP
$diagnostics['php'] = [
    'version' => phpversion(),
    'pdo' => extension_loaded('pdo') ? '✅ OK' : '❌ Manquant',
    'pdo_mysql' => extension_loaded('pdo_mysql') ? '✅ OK' : '❌ Manquant',
    'session.save_path' => session_save_path(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
];

// 5. Test du serveur
$diagnostics['server'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_sapi' => php_sapi_name(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'current_dir' => __DIR__,
];

// 6. Vérifier les erreurs dans les logs
$diagnostics['errors'] = [
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => error_reporting(),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Système</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-6">
            <div class="flex items-center mb-6">
                <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-stethoscope text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Diagnostic Système</h1>
                    <p class="text-gray-600">Vérification complète de votre installation</p>
                </div>
            </div>

            <!-- SESSION -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-user-circle mr-2 text-blue-500"></i>
                    État de la Session
                </h2>
                <div class="bg-gray-50 rounded-xl p-4">
                    <?php foreach ($diagnostics['session'] as $key => $value): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200 last:border-0">
                            <span class="font-semibold text-gray-700"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- DATABASE -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-database mr-2 text-green-500"></i>
                    Base de données
                </h2>
                <div class="bg-gray-50 rounded-xl p-4">
                    <?php foreach ($diagnostics['database'] as $key => $value): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200 last:border-0">
                            <span class="font-semibold text-gray-700"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- FILES -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-file-code mr-2 text-purple-500"></i>
                    Fichiers requis
                </h2>
                <div class="bg-gray-50 rounded-xl p-4">
                    <?php foreach ($diagnostics['files'] as $file => $status): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200 last:border-0">
                            <span class="font-semibold text-gray-700"><?= htmlspecialchars($file) ?>:</span>
                            <span class="text-gray-900"><?= $status ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PHP -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fab fa-php mr-2 text-indigo-500"></i>
                    Configuration PHP
                </h2>
                <div class="bg-gray-50 rounded-xl p-4">
                    <?php foreach ($diagnostics['php'] as $key => $value): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200 last:border-0">
                            <span class="font-semibold text-gray-700"><?= htmlspecialchars($key) ?>:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SERVER -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-server mr-2 text-orange-500"></i>
                    Serveur
                </h2>
                <div class="bg-gray-50 rounded-xl p-4">
                    <?php foreach ($diagnostics['server'] as $key => $value): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200 last:border-0">
                            <span class="font-semibold text-gray-700"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                            <span class="text-gray-900 break-all"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Test de connexion au dashboard -->
            <div class="mt-8 p-4 bg-blue-50 border-2 border-blue-200 rounded-xl">
                <h3 class="font-bold text-blue-900 mb-2">Actions de test</h3>
                <div class="space-y-2">
                    <a href="dashboard.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Accéder au Dashboard
                    </a>
                    <a href="reservations.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors ml-2">
                        <i class="fas fa-calendar-check mr-2"></i>Accéder aux Réservations
                    </a>
                    <button onclick="location.reload()" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors ml-2">
                        <i class="fas fa-sync-alt mr-2"></i>Rafraîchir
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>