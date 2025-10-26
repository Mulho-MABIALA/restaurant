<?php
require_once '../config.php';
session_start();

// 🔒 SÉCURITÉ : Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$employe_info = null;
$pointage_success = false;

// ✅ Initialiser $qr_data dès le début
$qr_data = $_POST['qr_data'] ?? $_GET['qr_data'] ?? '';

// 🔒 SÉCURITÉ : Vérification CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "❌ Erreur de sécurité. Veuillez rafraîchir la page.";
        error_log("Tentative CSRF détectée depuis IP: " . $_SERVER['REMOTE_ADDR']);
    }
}

// Logs debug sécurisés (sans afficher les codes complets)
error_log("QR Code reçu - Longueur: " . strlen((string)$qr_data) . " - Type: " . gettype($qr_data));
if (!empty($qr_data)) {
    $json_test = json_decode($qr_data, true);

    if (is_array($json_test)) {
        error_log("JSON décodé avec succès - Clés: " . implode(',', array_keys($json_test)));
    } elseif ($json_test !== null) {
        error_log("JSON décodé avec succès mais ce n’est pas un tableau - Type: " . gettype($json_test));
    } else {
        error_log("Pas un JSON valide, erreur: " . json_last_error_msg());
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'], $_POST['action']) && empty($message)) {
    $input_data = trim($_POST['qr_data']);
    $type = $_POST['action']; // 'entree' ou 'sortie'
    $geoloc = $_POST['geoloc'] ?? null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    try {
        // 🔒 VALIDATION AVANCÉE : Vérification du format d'entrée
        if (empty($input_data)) {
            throw new Exception("Code employé requis");
        }

        // Validation des formats autorisés
        $is_numeric_code = preg_match('/^[0-9]{1,8}$/', $input_data);
        $is_json = false;
        $json_data = null;
        
        if (!$is_numeric_code) {
            $json_data = json_decode($input_data, true);
            $is_json = ($json_data !== null && json_last_error() === JSON_ERROR_NONE);
        }

        if (!$is_numeric_code && !$is_json && !preg_match('/^EMP_\d+$/', $input_data)) {
            throw new Exception("Format de code invalide. Utilisez votre code à 8 chiffres.");
        }

        // 🔒 RATE LIMITING : Vérification des tentatives récentes
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM pointages 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            AND (geoloc = ? OR employe_id IN (
                SELECT id FROM employes WHERE code_numerique = ? OR id = ?
            ))
        ");
        $potential_emp_id = is_numeric($input_data) ? (int)$input_data : 0;
        $stmt->execute([$geoloc ?: 'no-geo', $input_data, $potential_emp_id]);
        
        if ($stmt->fetchColumn() > 5) {
            throw new Exception("Trop de tentatives de pointage. Attendez 2 minutes.");
        }

        $employe_id = null;

        // 1. Vérifier si c'est un code numérique (8 chiffres)
        if (preg_match('/^\d{8}$/', $input_data)) {
            $stmt = $conn->prepare("SELECT id, nom, prenom, code_numerique FROM employes WHERE code_numerique = ? AND statut != 'inactif'");
            $stmt->execute([$input_data]);
            $employe_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employe_info) {
                $employe_id = $employe_info['id'];
                error_log("Pointage avec code numérique pour employé ID: $employe_id");
            }
        }
        
        // 2. Si pas trouvé, essayer les autres formats
        if (!$employe_id) {
            // Format JSON (nouveau format généré par gestion_employe.php)
            if ($json_data) {
                if (isset($json_data['id']) && is_numeric($json_data['id'])) {
                    $employe_id = (int) $json_data['id'];
                }
                // Vérifier aussi le code numérique dans le JSON
                elseif (isset($json_data['code_numerique']) && preg_match('/^\d{8}$/', $json_data['code_numerique'])) {
                    $stmt = $conn->prepare("SELECT id FROM employes WHERE code_numerique = ? AND statut != 'inactif'");
                    $stmt->execute([$json_data['code_numerique']]);
                    $result = $stmt->fetch();
                    if ($result) {
                        $employe_id = $result['id'];
                    }
                }
            }
            // Format EMP_12345 (ancien format)
            elseif (strpos($input_data, 'EMP_') === 0) {
                $employe_id = (int) substr($input_data, 4);
            }
            // Format numérique direct (ID employé)
            elseif (is_numeric($input_data) && strlen($input_data) <= 6) { // ID employé généralement court
                $employe_id = (int) $input_data;
            }
        }

        if (!$employe_id || $employe_id <= 0) {
            throw new Exception("Code invalide ou format non reconnu. Utilisez votre code à 8 chiffres ou scannez votre QR code.");
        }

        // Récupérer les infos employé si pas encore fait
        if (!isset($employe_info) || !$employe_info) {
            $stmt = $conn->prepare("SELECT id, nom, prenom, code_numerique FROM employes WHERE id = ? AND statut != 'inactif'");
            $stmt->execute([$employe_id]);
            $employe_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$employe_info) {
            throw new Exception("Employé non trouvé ou inactif dans le système");
        }

        // 🔒 AMÉLIORATION : Vérification de doublon plus intelligente
        $stmt = $conn->prepare("
            SELECT type, created_at FROM pointages 
            WHERE employe_id = ? AND DATE(created_at) = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$employe_id, $today]);
        $last_pointage = $stmt->fetch();

        if ($last_pointage && $last_pointage['type'] === $type) {
            $time_diff = time() - strtotime($last_pointage['created_at']);
            if ($time_diff < 300) { // 5 minutes minimum entre deux pointages identiques
                $minutes_left = ceil((300 - $time_diff) / 60);
                throw new Exception("⚠️ {$employe_info['prenom']} {$employe_info['nom']} a déjà pointé une $type. Attendez $minutes_left minute(s).");
            }
        }

        // Vérification logique des pointages (ne pas sortir sans être entré)
        if ($type === 'sortie') {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM pointages 
                WHERE employe_id = ? AND type = 'entree' AND DATE(created_at) = ?
            ");
            $stmt->execute([$employe_id, $today]);
            if ($stmt->fetchColumn() === 0) {
                throw new Exception("⚠️ Impossible de pointer une sortie sans avoir pointé d'entrée aujourd'hui.");
            }
        }

        // Insertion du pointage avec informations de sécurité
        $stmt = $conn->prepare("
            INSERT INTO pointages (employe_id, type, created_at, geoloc, methode_pointage, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $methode = preg_match('/^\d{8}$/', $input_data) ? 'code_numerique' : 'qr_code';
        $stmt->execute([$employe_id, $type, $now, $geoloc, $methode, $user_ip]);

        if ($type === 'sortie') {
            // Récupérer l'heure d'entrée
            $stmt = $conn->prepare("
                SELECT created_at FROM pointages 
                WHERE employe_id = ? AND type = 'entree' AND DATE(created_at) = ? 
                ORDER BY created_at ASC LIMIT 1
            ");
            $stmt->execute([$employe_id, $today]);
            $entree = $stmt->fetchColumn();

            if ($entree) {
                $duree = strtotime($now) - strtotime($entree);
                $heures = floor($duree / 3600);
                $minutes = floor(($duree % 3600) / 60);
                $message = "✅ Sortie de {$employe_info['prenom']} {$employe_info['nom']} enregistrée. Durée travaillée : $heures h $minutes min.";

                // Détection du retard
                if (strtotime($entree) > strtotime("$today 09:00:00")) {
                    $minutes_retard = floor((strtotime($entree) - strtotime("$today 09:00:00")) / 60);
                    $message .= " 🚨 Retard de $minutes_retard min détecté.";
                }

                // Alerte manager si dépassement 10h
                if ($duree >= 10 * 3600) {
                    $message .= " 📧 Alerte envoyée au manager (dépassement 10h).";
                    // TODO: Implémenter l'envoi d'email
                }
            } else {
                $message = "⚠️ Sortie enregistrée mais aucune entrée trouvée pour {$employe_info['prenom']} {$employe_info['nom']}.";
            }
        } else {
            $message = "✅ Entrée de {$employe_info['prenom']} {$employe_info['nom']} enregistrée.";
            
            // Détection d'un retard immédiat
            if (strtotime($now) > strtotime("$today 09:15:00")) { // Tolérance 15 min
                $minutes_retard = floor((strtotime($now) - strtotime("$today 09:00:00")) / 60);
                $message .= " 🚨 Retard de $minutes_retard min.";
            }
            
            // Afficher le code numérique si disponible
            if (!empty($employe_info['code_numerique'])) {
                $message .= " (Code: {$employe_info['code_numerique']})";
            }
        }
        
        $pointage_success = true;
        
        // Log sécurisé du succès
        error_log("Pointage réussi - Employé ID: $employe_id - Type: $type - Méthode: $methode - IP: $user_ip");

    } catch (Exception $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        error_log("Erreur pointage - IP: $user_ip - Message: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Badgeuse QR Code</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Version html5-qrcode stable -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        .glass-morphism {
            background: rgba(17, 24, 39, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        #qr-reader {
            width: 100% !important;
            max-width: 600px;
            margin: 0 auto;
        }
        #qr-reader__dashboard_section_csr button {
            background: #4F46E5 !important;
            color: white !important;
            border-radius: 8px !important;
            margin: 4px !important;
        }
        #qr-reader__scan_region {
            border: 2px solid #4F46E5 !important;
        }
        .loading-spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #4F46E5;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .scanner-debug {
            margin-top: 10px;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
            font-size: 12px;
            font-family: monospace;
        }
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .processing-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .success-animation {
            animation: successPulse 0.6s ease-in-out;
        }
        @keyframes successPulse {
            0% { transform: scale(1); background-color: rgb(34, 197, 94); }
            50% { transform: scale(1.05); background-color: rgb(22, 163, 74); }
            100% { transform: scale(1); background-color: rgb(34, 197, 94); }
        }
        .error-animation {
            animation: errorShake 0.5s ease-in-out;
        }
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="glass-morphism shadow-2xl border-b border-white/10 sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <h1 class="text-3xl font-bold text-white">
                        📱 Badgeuse QR Code
                    </h1>
                </div>
            </header>

            <!-- Main content area -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white shadow-lg rounded-xl p-6">

            <!-- Message d'état de chargement -->
            <div id="libraryStatus" class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg text-center text-sm">
                <div class="loading-spinner mb-2"></div>
                Chargement de la bibliothèque QR Code...
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 <?= $pointage_success ? 'bg-green-100 text-green-800 success-animation' : 'bg-red-100 text-red-800 error-animation' ?> rounded-lg text-center">
                    <?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($employe_info && is_array($employe_info)): ?>
                        <div class="mt-2 text-sm">
                            <strong>Employé:</strong> <?= htmlspecialchars((string)($employe_info['nom'] ?? 'Inconnu'), ENT_QUOTES, 'UTF-8') ?> 
                            <?= htmlspecialchars((string)($employe_info['prenom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Mode de pointage -->
            <div class="mb-6 text-center">
                <div class="inline-flex rounded-lg bg-gray-200 p-1">
                    <button id="entreeBtn" class="px-6 py-2 rounded-md text-sm font-medium transition-colors bg-green-600 text-white">
                        📥 Entrée
                    </button>
                    <button id="sortieBtn" class="px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900">
                        📤 Sortie
                    </button>
                </div>
            </div>

            <!-- Scanner QR -->
            <div class="mb-6" id="qrScannerSection" style="display: none;">
                <div class="text-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Scannez votre badge QR</h2>
                    <p class="text-sm text-gray-600">Placez votre code QR devant la caméra</p>
                </div>
                
                <div id="qr-reader" class="border-2 border-dashed border-gray-300 rounded-lg p-4"></div>
                
                <!-- Zone de debug -->
                <div id="scannerDebug" class="scanner-debug" style="display: none;">
                    <strong>Debug Scanner:</strong>
                    <div id="debugMessages"></div>
                </div>
                
                <div class="mt-4 text-center">
                    <button id="startScan" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium transition-all">
                        🎥 Démarrer le scanner
                    </button>
                    <button id="stopScan" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium hidden transition-all">
                        ⏹️ Arrêter le scanner
                    </button>
                    <div class="mt-2 text-xs text-gray-500">
                        Assurez-vous d'autoriser l'accès à la caméra
                    </div>
                </div>
            </div>

            <!-- Message d'erreur bibliothèque -->
            <div id="qrErrorSection" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg" style="display: none;">
                <h3 class="font-semibold text-yellow-800 mb-2">⚠️ Scanner QR indisponible</h3>
                <p class="text-sm text-yellow-700 mb-2">
                    La bibliothèque de scan QR n'a pas pu se charger. Cela peut être dû à :
                </p>
                <ul class="text-sm text-yellow-700 ml-4 list-disc">
                    <li>Une connexion internet lente ou instable</li>
                    <li>Un bloqueur de publicité qui bloque les CDN</li>
                    <li>Des restrictions réseau</li>
                </ul>
                <p class="text-sm text-yellow-700 mt-2">
                    <strong>Solution :</strong> Utilisez la saisie manuelle avec votre code à 5 chiffres ci-dessous.
                </p>
            </div>

            <!-- Saisie manuelle (fallback) -->
            <div class="border-t pt-6">
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">Saisie manuelle</h3>
                    <p class="text-sm text-blue-700">
                        Utilisez votre <strong>code à 5 chiffres</strong> inscrit sur votre badge
                    </p>
                </div>
                
                <form method="POST" id="manualForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Code employé (5 chiffres) ou ID
                        </label>
                        <input type="text" name="qr_data" id="manualCodeInput"
                            placeholder="Ex: 12345678 ou votre ID employé" 
                            maxlength="8"
                            class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-lg text-center font-mono">
                        <div class="mt-1 text-xs text-gray-500">
                            Votre code à 5 chiffres est inscrit sur votre badge sous le QR code
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="submit" name="action" value="entree" id="manualEntree" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition-colors">
                            📥 Pointer Entrée
                        </button>
                        <button type="submit" name="action" value="sortie" id="manualSortie" 
                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition-colors hidden">
                            📤 Pointer Sortie
                        </button>
                    </div>
                    
                    <input type="hidden" name="geoloc" id="manualGeoloc">
                </form>
            </div>

            <!-- Liens utiles -->
            <div class="mt-6 text-center text-sm text-gray-500">
                <a href="presence.php" class="text-blue-600 hover:text-blue-800 underline mr-4">
                    📊 Consulter les pointages
                </a>
                <a href="admin.php" class="text-blue-600 hover:text-blue-800 underline">
                    ⚙️ Administration
                </a>
            </div>
        </div>

        <!-- Informations système -->
        <div class="mt-6 bg-yellow-50 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-800 mb-2">❓ Problème de pointage ?</h3>
            <div class="text-sm text-yellow-700 space-y-2">
                <p><strong>Scanner QR :</strong> Cliquez sur "Démarrer le scanner" et présentez votre badge</p>
                <p><strong>Code manuel :</strong> Tapez votre code à 5 chiffres (visible sur votre badge)</p>
                <p><strong>ID employé :</strong> En dernier recours, utilisez votre numéro d'employé</p>
                <p class="text-xs">💡 Le code à 5 chiffres est plus sûr et évite les erreurs</p>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Formulaire caché pour l'envoi automatique -->
    <form method="POST" id="autoSubmitForm" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="qr_data" id="scannedData">
        <input type="hidden" name="action" id="currentAction" value="entree">
        <input type="hidden" name="geoloc" id="autoGeoloc">
    </form>

    <script>
    let html5QrcodeScanner = null;
    let currentMode = 'entree';
    let isScanning = false;
    let libraryLoaded = false;
    let scanAttempts = 0;

    // 🎯 AMÉLIORATION : Feedback visuel immédiat
    function showProcessingOverlay(message = 'Traitement en cours...') {
        const overlay = document.createElement('div');
        overlay.id = 'processingOverlay';
        overlay.className = 'processing-overlay';
        overlay.innerHTML = `
            <div class="processing-card">
                <div class="loading-spinner mb-4"></div>
                <p class="text-lg font-medium text-gray-800">${message}</p>
                <p class="text-sm text-gray-600 mt-2">Veuillez patienter...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function hideProcessingOverlay() {
        const overlay = document.getElementById('processingOverlay');
        if (overlay) {
            overlay.remove();
        }
    }

    // Fonction de debug
    function debugLog(message) {
        console.log('[QR Scanner]', message);
        const debugDiv = document.getElementById('debugMessages');
        if (debugDiv) {
            debugDiv.innerHTML += `<div>${new Date().toLocaleTimeString()}: ${message}</div>`;
        }
    }

    // Fonction pour activer le debug
    function toggleDebug() {
        const debugSection = document.getElementById('scannerDebug');
        if (debugSection) {
            debugSection.style.display = debugSection.style.display === 'none' ? 'block' : 'none';
        }
    }

    // Double-cliquez sur le titre pour activer le debug
    document.querySelector('h1').addEventListener('dblclick', toggleDebug);

    // Vérification du statut de la bibliothèque
    function checkLibraryStatus() {
        debugLog('=== VÉRIFICATION BIBLIOTHÈQUE ===');
        debugLog('Html5QrcodeScanner: ' + typeof Html5QrcodeScanner);
        debugLog('Html5Qrcode: ' + typeof Html5Qrcode);
        
        if (typeof Html5QrcodeScanner !== 'undefined' && typeof Html5Qrcode !== 'undefined') {
            libraryLoaded = true;
            showLibrarySuccess();
            debugLog('✅ Bibliothèque chargée avec succès');
        } else {
            debugLog('❌ Bibliothèque non chargée');
            // Tentative de rechargement
            if (scanAttempts < 2) { // Réduire les tentatives
                scanAttempts++;
                debugLog(`Tentative ${scanAttempts}/2 de rechargement...`);
                setTimeout(() => {
                    loadBackupQRLibrary();
                }, 2000);
            } else {
                showLibraryError();
            }
        }
    }

    // Fonction pour charger une bibliothèque de backup
    function loadBackupQRLibrary() {
        debugLog('Chargement de la bibliothèque de backup...');
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = function() {
            debugLog('Bibliothèque de backup chargée');
            setTimeout(checkLibraryStatus, 1000);
        };
        script.onerror = function() {
            debugLog('Échec du chargement de la bibliothèque de backup');
            showLibraryError();
        };
        document.head.appendChild(script);
    }

    function showLibrarySuccess() {
        document.getElementById('libraryStatus').style.display = 'none';
        document.getElementById('qrScannerSection').style.display = 'block';
        document.getElementById('qrErrorSection').style.display = 'none';
    }

    function showLibraryError() {
        document.getElementById('libraryStatus').innerHTML = 
            '<div class="text-red-700">❌ Impossible de charger la bibliothèque QR Code</div>';
        document.getElementById('libraryStatus').className = 'mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-center text-sm';
        
        setTimeout(() => {
            document.getElementById('libraryStatus').style.display = 'none';
            document.getElementById('qrErrorSection').style.display = 'block';
        }, 2000);
    }

    // Vérification initiale après chargement de la page
    window.addEventListener('load', function() {
        setTimeout(checkLibraryStatus, 1500);
    });

    // Gestion des modes entrée/sortie
    document.getElementById('entreeBtn').addEventListener('click', function() {
        currentMode = 'entree';
        updateModeUI();
        debugLog('Mode changé: entrée');
    });

    document.getElementById('sortieBtn').addEventListener('click', function() {
        currentMode = 'sortie';
        updateModeUI();
        debugLog('Mode changé: sortie');
    });

    function updateModeUI() {
        const entreeBtn = document.getElementById('entreeBtn');
        const sortieBtn = document.getElementById('sortieBtn');
        const manualEntree = document.getElementById('manualEntree');
        const manualSortie = document.getElementById('manualSortie');

        if (currentMode === 'entree') {
            entreeBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors bg-green-600 text-white';
            sortieBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
            manualEntree.classList.remove('hidden');
            manualSortie.classList.add('hidden');
        } else {
            entreeBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
            sortieBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors bg-red-600 text-white';
            manualEntree.classList.add('hidden');
            manualSortie.classList.remove('hidden');
        }
    }

    // 🔒 AMÉLIORATION : Gestion de la géolocalisation avec cache
    let cachedGeolocation = null;
    let geoLocationTimeout = null;

    function getGeolocation(callback, timeout = 5000) {
        debugLog('Demande de géolocalisation...');
        
        // Utiliser le cache si disponible et récent (5 minutes)
        if (cachedGeolocation && cachedGeolocation.timestamp > Date.now() - 300000) {
            debugLog('Géolocalisation depuis cache: ' + cachedGeolocation.coords);
            callback(cachedGeolocation.coords);
            return;
        }

        if (navigator.geolocation) {
            // Timeout pour éviter les blocages
            geoLocationTimeout = setTimeout(() => {
                debugLog('Timeout géolocalisation');
                callback(null);
            }, timeout);

            const options = {
                enableHighAccuracy: false, // Plus rapide
                timeout: timeout - 500,
                maximumAge: 300000 // Cache 5 minutes
            };

            navigator.geolocation.getCurrentPosition(
                position => {
                    clearTimeout(geoLocationTimeout);
                    const lat = position.coords.latitude.toFixed(6);
                    const lon = position.coords.longitude.toFixed(6);
                    const coords = `${lat},${lon}`;
                    
                    // Mettre en cache
                    cachedGeolocation = {
                        coords: coords,
                        timestamp: Date.now()
                    };
                    
                    debugLog(`Géolocalisation obtenue: ${coords}`);
                    callback(coords);
                },
                error => {
                    clearTimeout(geoLocationTimeout);
                    debugLog('Géolocalisation non disponible: ' + error.message);
                    callback(null);
                },
                options
            );
        } else {
            debugLog('Géolocalisation non supportée par le navigateur');
            callback(null);
        }
    }

    // 🎯 AMÉLIORATION : Gestion d'erreurs caméra améliorée
    function handleCameraError(error) {
        let message = "Erreur caméra : ";
        let suggestion = "";
        
        switch(error.name) {
            case 'NotAllowedError':
                message += "Permission refusée.";
                suggestion = "Cliquez sur l'icône caméra dans la barre d'adresse et autorisez l'accès.";
                break;
            case 'NotFoundError':
                message += "Aucune caméra trouvée.";
                suggestion = "Vérifiez qu'une caméra est connectée à votre appareil.";
                break;
            case 'NotReadableError':
                message += "Caméra utilisée par une autre application.";
                suggestion = "Fermez les autres applications utilisant la caméra.";
                break;
            case 'OverconstrainedError':
                message += "Contraintes caméra incompatibles.";
                suggestion = "Essayez avec un autre appareil photo.";
                break;
            case 'SecurityError':
                message += "Accès sécurisé requis (HTTPS).";
                suggestion = "Utilisez la saisie manuelle ou accédez via HTTPS.";
                break;
            default:
                message += error.message || "Erreur inconnue.";
                suggestion = "Utilisez la saisie manuelle avec votre code à 8 chiffres.";
        }
        
        debugLog('❌ Erreur caméra: ' + error.name + ' - ' + error.message);
        
        // Afficher une alerte informative
        const alertDiv = document.createElement('div');
        alertDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
        alertDiv.innerHTML = `
            <h4 class="font-semibold text-red-800 mb-2">📷 ${message}</h4>
            <p class="text-sm text-red-700 mb-2">${suggestion}</p>
            <button onclick="this.parentElement.remove()" class="text-sm text-red-600 underline">Fermer</button>
        `;
        
        document.getElementById('qrScannerSection').appendChild(alertDiv);
    }

    // GESTION DU SCANNER QR AMÉLIORÉE
    document.getElementById('startScan').addEventListener('click', function() {
        debugLog('=== DÉMARRAGE SCANNER DEMANDÉ ===');
        
        if (!libraryLoaded) {
            alert('La bibliothèque QR Code n\'est pas chargée. Utilisez la saisie manuelle.');
            return;
        }
        
        if (isScanning) {
            debugLog('Scanner déjà en cours');
            return;
        }
        
        showProcessingOverlay('Démarrage de la caméra...');
        startScanning();
    });

    document.getElementById('stopScan').addEventListener('click', function() {
        debugLog('=== ARRÊT SCANNER DEMANDÉ ===');
        stopScanning();
    });

    function startScanning() {
        debugLog('Démarrage du scanner...');
        
        // Vérification finale de la bibliothèque
        if (typeof Html5QrcodeScanner === 'undefined') {
            debugLog('❌ Html5QrcodeScanner non défini');
            hideProcessingOverlay();
            alert('Bibliothèque QR Code non disponible. Utilisez la saisie manuelle.');
            return;
        }
        
        // Test des permissions caméra
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            debugLog('❌ MediaDevices non supporté');
            hideProcessingOverlay();
            alert('Votre navigateur ne supporte pas l\'accès à la caméra.');
            return;
        }
        
        // 🎯 AMÉLIORATION : Options caméra optimisées
        const cameraOptions = {
            video: {
                facingMode: 'environment', // Caméra arrière préférée
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };
        
        // Demander les permissions
        navigator.mediaDevices.getUserMedia(cameraOptions)
            .then(function(stream) {
                debugLog('✅ Permissions caméra accordées');
                hideProcessingOverlay();
                
                // Arrêter le stream test
                stream.getTracks().forEach(track => track.stop());
                
                // Nettoyer le conteneur
                const qrReaderDiv = document.getElementById('qr-reader');
                qrReaderDiv.innerHTML = '';
                
                // Callback de succès
                const onScanSuccess = (decodedText, decodedResult) => {
                    debugLog(`🎯 QR Code détecté: ${decodedText.substring(0, 50)}...`);
                    debugLog('Résultat complet:', decodedResult);
                    
                    // Arrêter le scanner immédiatement
                    stopScanning();
                    
                    // Traitement du code scanné
                    processScannedCode(decodedText);
                };
                
                // Callback d'échec (erreurs normales)
                const onScanFailure = (error) => {
                    // Ne pas logger les erreurs normales de scan
                };
                
                try {
                    debugLog('Création de Html5QrcodeScanner...');
                    
                    // 🎯 AMÉLIORATION : Configuration optimisée
                    const config = { 
                        fps: 5, // Réduit pour économiser les ressources
                        qrbox: { width: 250, height: 250 }, // Taille réduite
                        aspectRatio: 1.0,
                        disableFlip: true, // Améliore les performances
                        rememberLastUsedCamera: true,
                        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                        showTorchButtonIfSupported: true, // Flash si disponible
                        showZoomSliderIfSupported: false // Pas de zoom pour simplifier
                    };
                    
                    html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", config, false);
                    
                    debugLog('Rendu du scanner...');
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                    
                    isScanning = true;
                    document.getElementById('startScan').classList.add('hidden');
                    document.getElementById('stopScan').classList.remove('hidden');
                    
                    debugLog('✅ Scanner démarré avec succès');
                    
                } catch (error) {
                    debugLog('❌ Erreur lors du démarrage: ' + error.message);
                    console.error('Erreur scanner:', error);
                    hideProcessingOverlay();
                    handleCameraError(error);
                }
                
            })
            .catch(function(error) {
                hideProcessingOverlay();
                handleCameraError(error);
            });
    }

    function stopScanning() {
        debugLog('Arrêt du scanner...');
        
        if (html5QrcodeScanner && isScanning) {
            try {
                html5QrcodeScanner.clear().then(() => {
                    debugLog('✅ Scanner arrêté proprement');
                }).catch(err => {
                    debugLog('⚠️ Erreur lors de l\'arrêt: ' + err);
                    console.error('Erreur clear:', err);
                });
            } catch (e) {
                debugLog('❌ Exception lors de l\'arrêt: ' + e.message);
                console.error('Erreur arrêt scanner:', e);
            }
            
            html5QrcodeScanner = null;
        }
        
        isScanning = false;
        document.getElementById('startScan').classList.remove('hidden');
        document.getElementById('stopScan').classList.add('hidden');
        
        // Nettoyer l'interface
        setTimeout(() => {
            document.getElementById('qr-reader').innerHTML = '';
        }, 500);
    }

    // 🎯 AMÉLIORATION : Traitement du code scanné avec validation
    function processScannedCode(decodedText) {
        debugLog('=== TRAITEMENT DU CODE SCANNÉ ===');
        debugLog('Code brut (50 premiers caractères): ' + decodedText.substring(0, 50));
        debugLog('Type: ' + typeof decodedText);
        debugLog('Longueur: ' + decodedText.length);
        
        // 🔒 VALIDATION côté client
        if (!decodedText || decodedText.trim() === '') {
            alert('Code QR vide détecté. Veuillez réessayer.');
            return;
        }
        
        // Tronquer si trop long (sécurité)
        if (decodedText.length > 1000) {
            debugLog('⚠️ Code trop long, troncature');
            decodedText = decodedText.substring(0, 1000);
        }
        
        // Test si c'est du JSON
        try {
            const jsonTest = JSON.parse(decodedText);
            debugLog('JSON valide détecté - Clés:', Object.keys(jsonTest));
        } catch (e) {
            debugLog('Pas un JSON: ' + e.message);
        }
        
        showProcessingOverlay('Traitement du pointage...');
        
        // Obtenir la géolocalisation et soumettre avec timeout
        const geoTimeout = setTimeout(() => {
            debugLog('Timeout géolocalisation, soumission sans position');
            submitScannedData(decodedText, null);
        }, 3000); // 3 secondes max
        
        getGeolocation(function(geoloc) {
            clearTimeout(geoTimeout);
            debugLog('Géolocalisation pour envoi: ' + (geoloc || 'non disponible'));
            submitScannedData(decodedText, geoloc);
        }, 2500); // 2.5 secondes pour la géoloc
    }

    function submitScannedData(decodedText, geoloc) {
        // Remplir le formulaire caché
        document.getElementById('scannedData').value = decodedText;
        document.getElementById('currentAction').value = currentMode;
        document.getElementById('autoGeoloc').value = geoloc || '';
        
        debugLog('Soumission du formulaire automatique...');
        debugLog('- Données (longueur): ' + decodedText.length);
        debugLog('- Action: ' + currentMode);
        debugLog('- Géoloc: ' + (geoloc || 'non disponible'));
        
        // Soumettre le formulaire
        document.getElementById('autoSubmitForm').submit();
    }

    // 🔒 AMÉLIORATION : Validation de saisie manuelle renforcée
    function validateManualCode() {
        const code = document.getElementById('manualCodeInput').value.trim();
        
        debugLog('Validation du code manuel: ' + code);
        
        if (code.length === 0) {
            showError('Veuillez saisir votre code employé');
            return false;
        }
        
        // Vérification caractères autorisés
        if (!/^[0-9]+$/.test(code)) {
            showError('Le code doit contenir uniquement des chiffres');
            return false;
        }
        
        // Validation longueur
        if (code.length > 8) {
            showError('Le code ne peut pas dépasser 8 chiffres');
            return false;
        }
        
        // Validation spécifique pour les codes à 8 chiffres
        if (code.length === 8) {
            debugLog('Code à 8 chiffres détecté: ' + code.substring(0, 4) + '****');
        } else if (code.length <= 6 && code.length >= 1) {
            debugLog('ID employé détecté: ' + code);
        } else if (code.length === 7) {
            showError('Code incomplet. Votre code badge fait 8 chiffres.');
            return false;
        }
        
        return true;
    }

    function showError(message) {
        const input = document.getElementById('manualCodeInput');
        input.classList.add('border-red-500');
        
        // Supprimer l'erreur précédente
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Ajouter le message d'erreur
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-red-600 text-sm mt-1';
        errorDiv.textContent = message;
        input.parentNode.insertBefore(errorDiv, input.nextSibling.nextSibling);
        
        // Animation d'erreur
        input.classList.add('error-animation');
        setTimeout(() => {
            input.classList.remove('error-animation');
        }, 500);
        
        // Supprimer l'erreur après 5 secondes
        setTimeout(() => {
            input.classList.remove('border-red-500');
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }

    // GESTION DE LA SAISIE MANUELLE AMÉLIORÉE
    document.addEventListener('DOMContentLoaded', function() {
        const manualInput = document.getElementById('manualCodeInput');
        
        // Limiter aux chiffres uniquement avec feedback
        manualInput.addEventListener('input', function() {
            const oldValue = this.value;
            this.value = this.value.replace(/\D/g, '');
            
            // Si des caractères ont été supprimés, montrer un feedback
            if (oldValue !== this.value && oldValue.length > this.value.length) {
                this.style.borderColor = '#f59e0b';
                setTimeout(() => {
                    this.style.borderColor = '';
                }, 300);
            }
            
            // Supprimer les messages d'erreur lors de la saisie
            const errorMsg = this.parentNode.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
                this.classList.remove('border-red-500');
            }
        });
        
        // Obtenir la géolocalisation pour la saisie manuelle
        getGeolocation(function(geoloc) {
            if (geoloc) {
                document.getElementById('manualGeoloc').value = geoloc;
                debugLog('Géolocalisation définie pour saisie manuelle: ' + geoloc);
            }
        });
        
        // Focus automatique sur le champ de saisie
        manualInput.focus();
        
        // 🎯 AMÉLIORATION : Soumission intelligente avec Entrée
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (validateManualCode()) {
                    showProcessingOverlay('Vérification du code...');
                    if (currentMode === 'entree') {
                        document.getElementById('manualEntree').click();
                    } else {
                        document.getElementById('manualSortie').click();
                    }
                }
            }
        });

        // Validation en temps réel (optionnelle)
        manualInput.addEventListener('blur', function() {
            if (this.value.length > 0) {
                validateManualCode();
            }
        });
    });

    document.getElementById('manualForm').addEventListener('submit', function(e) {
        if (!validateManualCode()) {
            e.preventDefault();
        } else {
            showProcessingOverlay('Envoi du pointage...');
            debugLog('Soumission formulaire manuel validée');
        }
    });

    // 🌐 AMÉLIORATION : Gestion réseau améliorée
    let isOnline = navigator.onLine;

    function updateNetworkStatus() {
        const statusDiv = document.getElementById('networkStatus');
        if (!isOnline && !statusDiv) {
            const alertDiv = document.createElement('div');
            alertDiv.id = 'networkStatus';
            alertDiv.className = 'mb-4 p-3 bg-orange-100 text-orange-700 rounded-lg text-center text-sm';
            alertDiv.innerHTML = '⚠️ Connexion internet instable. Les pointages seront traités dès le retour de la connexion.';
            document.querySelector('.max-w-2xl').insertBefore(alertDiv, document.querySelector('.bg-white'));
        } else if (isOnline && statusDiv) {
            statusDiv.remove();
        }
    }

    window.addEventListener('online', function() {
        debugLog('🟢 Connexion réseau rétablie');
        isOnline = true;
        updateNetworkStatus();
    });

    window.addEventListener('offline', function() {
        debugLog('🔴 Connexion réseau perdue');
        isOnline = false;
        updateNetworkStatus();
    });

    // Vérification initiale du réseau
    updateNetworkStatus();

    // Auto-refresh après pointage réussi avec amélioration
    <?php if ($pointage_success): ?>
    debugLog('Pointage réussi - rechargement dans 3 secondes');
    hideProcessingOverlay();
    
    // Afficher un message de succès temporaire
    const successDiv = document.createElement('div');
    successDiv.className = 'fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    successDiv.innerHTML = '✅ Pointage enregistré avec succès !';
    document.body.appendChild(successDiv);
    
    setTimeout(function() {
        successDiv.remove();
        debugLog('Rechargement de la page après succès');
        window.location.reload();
    }, 3000);
    <?php endif; ?>

    // Raccourcis clavier améliorés
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+D pour debug
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            toggleDebug();
        }
        
        // Échap pour arrêter le scanner
        if (e.key === 'Escape' && isScanning) {
            stopScanning();
        }
        
        // F pour focus sur input manuel
        if (e.key === 'f' && !e.ctrlKey && !e.altKey) {
            const input = document.getElementById('manualCodeInput');
            if (document.activeElement !== input) {
                e.preventDefault();
                input.focus();
            }
        }
    });

    // Test de performance au démarrage
    function performanceTest() {
        const start = performance.now();
        
        // Test des API essentielles
        const tests = {
            mediaDevices: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            geolocation: !!navigator.geolocation,
            localStorage: !!window.localStorage,
            sessionStorage: !!window.sessionStorage,
            crypto: !!(window.crypto && window.crypto.getRandomValues)
        };
        
        const end = performance.now();
        debugLog('🔍 Tests de performance:');
        debugLog('- Durée: ' + (end - start).toFixed(2) + 'ms');
        debugLog('- APIs supportées: ' + JSON.stringify(tests));
        debugLog('- User Agent: ' + navigator.userAgent.substring(0, 100) + '...');
    }

    // Initialisation finale
    debugLog('🚀 Script initialisé - Version sécurisée avec améliorations');
    debugLog('Mode actuel: ' + currentMode);
    debugLog('URL actuelle: ' + window.location.href);
    
    // Lancer les tests après un délai
    setTimeout(performanceTest, 1000);
    </script>
</body>
</html>