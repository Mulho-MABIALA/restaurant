<?php
// admin_newsletter_import.php
require_once '../config.php';
session_start();

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$import_results = [];

// Traitement de l'upload et validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        // Étape 1 : Upload du fichier
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/imports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = 'import_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.csv';
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $filepath)) {
                $_SESSION['import_file'] = $filepath;
                header('Location: ?step=2');
                exit;
            } else {
                $_SESSION['error_message'] = "Erreur lors de l'upload du fichier";
            }
        } else {
            $_SESSION['error_message'] = "Veuillez sélectionner un fichier CSV valide";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'process') {
        // Étape 3 : Traitement de l'import
        $filepath = $_SESSION['import_file'] ?? '';
        $mapping = $_POST['mapping'] ?? [];
        $options = $_POST['options'] ?? [];
        
        if (!file_exists($filepath)) {
            $_SESSION['error_message'] = "Fichier d'import introuvable";
            header('Location: ?step=1');
            exit;
        }
        
        $import_results = processImport($conn, $filepath, $mapping, $options);
        
        // Nettoyer le fichier temporaire
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        unset($_SESSION['import_file']);
        
        $step = 4; // Afficher les résultats
    }
}

// Fonction de traitement de l'import
function processImport($conn, $filepath, $mapping, $options) {
    $results = [
        'total_rows' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
        'duplicates' => 0
    ];
    
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $results['errors'][] = "Impossible de lire le fichier";
        return $results;
    }
    
    // Ignorer la première ligne si c'est un header
    if (isset($options['has_header']) && $options['has_header']) {
        fgetcsv($handle);
    }
    
    $row_number = isset($options['has_header']) && $options['has_header'] ? 2 : 1;
    
    // Préparer les requêtes
    $check_stmt = $conn->prepare("SELECT id, statut FROM newsletter WHERE email = ?");
    $insert_stmt = $conn->prepare("
        INSERT INTO newsletter (email, first_name, last_name, phone, statut, source, date_inscription, ip_address) 
        VALUES (?, ?, ?, ?, 'actif', 'import', NOW(), ?)
    ");
    $update_stmt = $conn->prepare("
        UPDATE newsletter 
        SET first_name = COALESCE(NULLIF(?, ''), first_name),
            last_name = COALESCE(NULLIF(?, ''), last_name),
            phone = COALESCE(NULLIF(?, ''), phone),
            statut = CASE WHEN statut = 'inactif' THEN 'actif' ELSE statut END,
            source = 'import_update'
        WHERE email = ?
    ");
    
    while (($row = fgetcsv($handle)) !== false) {
        $results['total_rows']++;
        
        try {
            // Extraire les données selon le mapping
            $email = isset($mapping['email']) && isset($row[$mapping['email']]) ? 
                     trim($row[$mapping['email']]) : '';
            $first_name = isset($mapping['first_name']) && isset($row[$mapping['first_name']]) ? 
                         trim($row[$mapping['first_name']]) : '';
            $last_name = isset($mapping['last_name']) && isset($row[$mapping['last_name']]) ? 
                        trim($row[$mapping['last_name']]) : '';
            $phone = isset($mapping['phone']) && isset($row[$mapping['phone']]) ? 
                    trim($row[$mapping['phone']]) : '';
            
            // Validation de l'email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['errors'][] = "Ligne $row_number: Email invalide ou manquant ($email)";
                $results['skipped']++;
                $row_number++;
                continue;
            }
            
            // Vérifier si l'email existe déjà
            $check_stmt->execute([$email]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Email existe déjà
                if (isset($options['update_existing']) && $options['update_existing']) {
                    $update_stmt->execute([$first_name, $last_name, $phone, $email]);
                    $results['updated']++;
                } else {
                    $results['duplicates']++;
                }
            } else {
                // Nouvel email
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $insert_stmt->execute([$email, $first_name, $last_name, $phone, $ip_address]);
                $results['imported']++;
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Ligne $row_number: " . $e->getMessage();
            $results['skipped']++;
        }
        
        $row_number++;
    }
    
    fclose($handle);
    
    return $results;
}

// Analyser le fichier CSV pour l'étape 2
$csv_preview = [];
$csv_headers = [];
if ($step === 2 && isset($_SESSION['import_file'])) {
    $filepath = $_SESSION['import_file'];
    if (file_exists($filepath)) {
        $handle = fopen($filepath, 'r');
        if ($handle) {
            // Lire les premières lignes pour prévisualisation
            $line_count = 0;
            while (($row = fgetcsv($handle)) !== false && $line_count < 10) {
                if ($line_count === 0) {
                    $csv_headers = $row;
                }
                $csv_preview[] = $row;
                $line_count++;
            }
            fclose($handle);
        }
    }
}

// Récupérer les segments pour l'attribution
$segments = [];
try {
    $segments = $conn->query("SELECT * FROM newsletter_segments WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $segments = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import d'Abonnés</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .step-indicator {
            @apply flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium;
        }
        .step-active {
            @apply bg-blue-600 text-white;
        }
        .step-completed {
            @apply bg-green-600 text-white;
        }
        .step-inactive {
            @apply bg-gray-300 text-gray-600;
        }
        .drag-drop-zone {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        .drag-drop-zone.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-upload text-blue-600 mr-3"></i>
                        Import d'Abonnés
                    </h1>
                    <p class="text-gray-600">Importez vos contacts depuis un fichier CSV</p>
                </div>
                <a href="admin_newsletter.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Indicateur d'étapes -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="step-indicator <?= $step >= 1 ? ($step > 1 ? 'step-completed' : 'step-active') : 'step-inactive' ?>">1</div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">Upload</div>
                        <div class="text-xs text-gray-500">Sélectionner le fichier CSV</div>
                    </div>
                </div>
                
                <div class="flex-1 mx-4">
                    <div class="h-1 bg-gray-200 rounded">
                        <div class="h-1 bg-blue-600 rounded transition-all duration-300" style="width: <?= min(100, ($step - 1) * 33.33) ?>%"></div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="step-indicator <?= $step >= 2 ? ($step > 2 ? 'step-completed' : 'step-active') : 'step-inactive' ?>">2</div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">Mapping</div>
                        <div class="text-xs text-gray-500">Associer les colonnes</div>
                    </div>
                </div>
                
                <div class="flex-1 mx-4">
                    <div class="h-1 bg-gray-200 rounded">
                        <div class="h-1 bg-blue-600 rounded transition-all duration-300" style="width: <?= min(100, ($step - 2) * 50) ?>%"></div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="step-indicator <?= $step >= 3 ? ($step > 3 ? 'step-completed' : 'step-active') : 'step-inactive' ?>">3</div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">Import</div>
                        <div class="text-xs text-gray-500">Traitement des données</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($step === 1): ?>
        <!-- Étape 1: Upload du fichier -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">
                <i class="fas fa-file-upload text-blue-500 mr-2"></i>
                Sélectionner votre fichier CSV
            </h3>
            
            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-medium text-blue-800 mb-2">Format requis :</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Fichier au format CSV (séparateur: virgule ou point-virgule)</li>
                    <li>• Encodage UTF-8 recommandé</li>
                    <li>• Colonnes supportées: email, prénom, nom, téléphone</li>
                    <li>• La colonne email est obligatoire</li>
                    <li>• Taille maximale: 10 MB</li>
                </ul>
            </div>
            
            <!-- Zone de téléchargement -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                
                <div class="drag-drop-zone rounded-lg p-8 text-center mb-6" id="dropZone">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                    <p class="text-lg font-medium text-gray-700 mb-2">Glissez-déposez votre fichier CSV ici</p>
                    <p class="text-sm text-gray-500 mb-4">ou cliquez pour parcourir</p>
                    
                    <input type="file" 
                           name="csv_file" 
                           accept=".csv,.txt" 
                           required 
                           class="hidden" 
                           id="csvFile">
                    
                    <button type="button" 
                            onclick="document.getElementById('csvFile').click()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-folder-open mr-2"></i>Parcourir les fichiers
                    </button>
                </div>
                
                <div id="fileInfo" class="hidden bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-file-csv text-2xl text-green-500 mr-3"></i>
                            <div>
                                <div class="font-medium text-gray-900" id="fileName"></div>
                                <div class="text-sm text-gray-500" id="fileSize"></div>
                            </div>
                        </div>
                        <button type="button" 
                                onclick="clearFile()" 
                                class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors disabled:opacity-50" 
                            id="uploadBtn" 
                            disabled>
                        <i class="fas fa-upload mr-2"></i>Télécharger et analyser
                    </button>
                </div>
            </form>
            
            <!-- Exemple de fichier -->
            <div class="mt-8 border-t pt-6">
                <h4 class="text-sm font-medium text-gray-800 mb-3">Exemple de fichier CSV :</h4>
                <div class="bg-gray-900 text-green-400 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                    <div>email,prenom,nom,telephone</div>
                    <div>jean.dupont@example.com,Jean,Dupont,0123456789</div>
                    <div>marie.martin@example.com,Marie,Martin,0987654321</div>
                    <div>pierre.durand@example.com,Pierre,Durand,</div>
                </div>
                
                <div class="mt-3">
                    <a href="admin_newsletter_template.csv" 
                       class="text-blue-600 hover:text-blue-700 text-sm">
                        <i class="fas fa-download mr-1"></i>Télécharger un modèle CSV
                    </a>
                </div>
            </div>
        </div>

        <?php elseif ($step === 2): ?>
        <!-- Étape 2: Mapping des colonnes -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">
                <i class="fas fa-columns text-blue-500 mr-2"></i>
                Associer les colonnes
            </h3>
            
            <?php if (!empty($csv_preview)): ?>
            <!-- Prévisualisation du fichier -->
            <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-800 mb-3">Prévisualisation du fichier :</h4>
                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php foreach ($csv_headers as $index => $header): ?>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Colonne <?= $index + 1 ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($csv_preview, 0, 5) as $row_index => $row): ?>
                            <tr class="<?= $row_index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                <?php foreach ($row as $cell): ?>
                                <td class="py-2 px-3 text-sm text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($cell) ?>">
                                    <?= htmlspecialchars($cell) ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($csv_preview) > 5): ?>
                <p class="text-xs text-gray-500 mt-2">... et <?= count($csv_preview) - 5 ?> lignes supplémentaires</p>
                <?php endif; ?>
            </div>
            
            <!-- Formulaire de mapping -->
            <form method="POST" id="mappingForm">
                <input type="hidden" name="action" value="process">
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Mapping des colonnes -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-800 mb-4">Association des colonnes :</h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <select name="mapping[email]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Sélectionner une colonne --</option>
                                    <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?= $index ?>" <?= (stripos($header, 'email') !== false || stripos($header, 'mail') !== false) ? 'selected' : '' ?>>
                                        Colonne <?= $index + 1 ?> (<?= htmlspecialchars(substr($header, 0, 30)) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <select name="mapping[first_name]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Ignorer cette colonne --</option>
                                    <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?= $index ?>" <?= (stripos($header, 'prenom') !== false || stripos($header, 'first') !== false) ? 'selected' : '' ?>>
                                        Colonne <?= $index + 1 ?> (<?= htmlspecialchars(substr($header, 0, 30)) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <select name="mapping[last_name]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Ignorer cette colonne --</option>
                                    <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?= $index ?>" <?= (stripos($header, 'nom') !== false || stripos($header, 'last') !== false) ? 'selected' : '' ?>>
                                        Colonne <?= $index + 1 ?> (<?= htmlspecialchars(substr($header, 0, 30)) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <select name="mapping[phone]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Ignorer cette colonne --</option>
                                    <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?= $index ?>" <?= (stripos($header, 'tel') !== false || stripos($header, 'phone') !== false) ? 'selected' : '' ?>>
                                        Colonne <?= $index + 1 ?> (<?= htmlspecialchars(substr($header, 0, 30)) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options d'import -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-800 mb-4">Options d'import :</h4>
                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="options[has_header]" value="1" checked class="mr-2">
                                <span class="text-sm text-gray-700">La première ligne contient les en-têtes</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="options[update_existing]" value="1" class="mr-2">
                                <span class="text-sm text-gray-700">Mettre à jour les abonnés existants</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="options[send_welcome]" value="1" class="mr-2">
                                <span class="text-sm text-gray-700">Envoyer un email de bienvenue</span>
                            </label>
                            
                            <?php if (!empty($segments)): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ajouter aux segments :</label>
                                <div class="space-y-2">
                                    <?php foreach ($segments as $segment): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="options[segments][]" value="<?= $segment['id'] ?>" class="mr-2">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($segment['name']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-between mt-8">
                    <a href="?step=1" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-play mr-2"></i>Lancer l'import
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                <p class="text-lg text-gray-700">Impossible de lire le fichier CSV</p>
                <a href="?step=1" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Retour à l'étape 1
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($step === 4): ?>
        <!-- Étape 4: Résultats de l'import -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                Import terminé
            </h3>
            
            <!-- Résumé des résultats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-600 text-sm font-medium">Total lignes</p>
                            <p class="text-2xl font-bold text-blue-900"><?= number_format($import_results['total_rows']) ?></p>
                        </div>
                        <i class="fas fa-file-alt text-2xl text-blue-400"></i>
                    </div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-600 text-sm font-medium">Importés</p>
                            <p class="text-2xl font-bold text-green-900"><?= number_format($import_results['imported']) ?></p>
                        </div>
                        <i class="fas fa-user-plus text-2xl text-green-400"></i>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-600 text-sm font-medium">Mis à jour</p>
                            <p class="text-2xl font-bold text-yellow-900"><?= number_format($import_results['updated']) ?></p>
                        </div>
                        <i class="fas fa-user-edit text-2xl text-yellow-400"></i>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-600 text-sm font-medium">Erreurs</p>
                            <p class="text-2xl font-bold text-red-900"><?= number_format($import_results['skipped']) ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-2xl text-red-400"></i>
                    </div>
                </div>
            </div>
            
            <!-- Détails -->
            <?php if ($import_results['duplicates'] > 0): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-700">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?= number_format($import_results['duplicates']) ?> email(s) déjà existant(s) ignoré(s)
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Erreurs détaillées -->
            <?php if (!empty($import_results['errors'])): ?>
            <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-800 mb-3">Erreurs détaillées :</h4>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 max-h-60 overflow-y-auto">
                    <ul class="text-sm text-red-700 space-y-1">
                        <?php foreach (array_slice($import_results['errors'], 0, 20) as $error): ?>
                        <li>• <?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($import_results['errors']) > 20): ?>
                        <li class="font-medium">... et <?= count($import_results['errors']) - 20 ?> erreurs supplémentaires</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="flex justify-between">
                <a href="?step=1" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fas fa-upload mr-2"></i>Nouvel import
                </a>
                <a href="admin_newsletter.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fas fa-users mr-2"></i>Voir les abonnés
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Gestion du drag & drop
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('csvFile');
    const fileInfo = document.getElementById('fileInfo');
    const uploadBtn = document.getElementById('uploadBtn');

    if (dropZone && fileInput) {
        // Empêcher les comportements par défaut
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Ajouter des classes visuelles
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        // Gérer le drop
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                displayFileInfo(files[0]);
            }
        }

        // Gérer la sélection de fichier
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                displayFileInfo(this.files[0]);
            }
        });

        function displayFileInfo(file) {
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            fileInfo.classList.remove('hidden');
            uploadBtn.disabled = false;
            
            // Validation du type de fichier
            if (!file.name.toLowerCase().endsWith('.csv') && !file.name.toLowerCase().endsWith('.txt')) {
                alert('Veuillez sélectionner un fichier CSV ou TXT');
                clearFile();
                return;
            }
            
            // Validation de la taille (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                alert('Le fichier est trop volumineux (maximum 10MB)');
                clearFile();
                return;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }

    function clearFile() {
        fileInput.value = '';
        fileInfo.classList.add('hidden');
        uploadBtn.disabled = true;
    }

    // Validation du formulaire de mapping
    const mappingForm = document.getElementById('mappingForm');
    if (mappingForm) {
        mappingForm.addEventListener('submit', function(e) {
            const emailMapping = document.querySelector('select[name="mapping[email]"]').value;
            if (!emailMapping) {
                e.preventDefault();
                alert('Veuillez sélectionner la colonne email');
                return;
            }
            
            // Confirmation avant import
            if (!confirm('Êtes-vous sûr de vouloir lancer l\'import ? Cette action ne peut pas être annulée.')) {
                e.preventDefault();
            }
        });
    }

    // Détection automatique des colonnes
    document.addEventListener('DOMContentLoaded', function() {
        const selects = document.querySelectorAll('select[name^="mapping"]');
        selects.forEach(select => {
            const fieldType = select.name.match(/\[(\w+)\]/)[1];
            autoDetectColumn(select, fieldType);
        });
    });

    function autoDetectColumn(select, fieldType) {
        const options = select.querySelectorAll('option');
        const patterns = {
            email: ['email', 'mail', 'e-mail', 'courriel'],
            first_name: ['prenom', 'prénom', 'first', 'firstname', 'fname'],
            last_name: ['nom', 'last', 'lastname', 'lname', 'surname'],
            phone: ['tel', 'telephone', 'phone', 'mobile', 'portable']
        };
        
        if (patterns[fieldType]) {
            for (let option of options) {
                const text = option.textContent.toLowerCase();
                if (patterns[fieldType].some(pattern => text.includes(pattern))) {
                    option.selected = true;
                    break;
                }
            }
        }
    }
    </script>
</body>
</html>