<?php
require_once '../config.php';
session_start();

$message = '';
$employe_info = null;
$pointage_success = false;

// ✅ Initialiser $qr_data dès le début
$qr_data = $_POST['qr_data'] ?? $_GET['qr_data'] ?? '';

// Logs debug
error_log("QR Code reçu: " . $qr_data);
error_log("Type de données: " . gettype($qr_data));
error_log("Longueur: " . strlen((string)$qr_data));

// Test si c'est du JSON valide
if (!empty($qr_data)) {
    $json_test = json_decode($qr_data, true);
    if ($json_test !== null) {
        error_log("JSON décodé avec succès: " . print_r($json_test, true));
    } else {
        error_log("Pas un JSON valide, erreur: " . json_last_error_msg());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'], $_POST['action'])) {
    $input_data = trim($_POST['qr_data']);
    $type = $_POST['action']; // 'entree' ou 'sortie'
    $geoloc = $_POST['geoloc'] ?? null;
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    try {
        $employe_id = null;

        // 1. Vérifier si c'est un code numérique (8 chiffres)
        if (preg_match('/^\d{8}$/', $input_data)) {
            $stmt = $conn->prepare("SELECT id, nom, prenom FROM employes WHERE code_numerique = ? AND statut != 'inactif'");
            $stmt->execute([$input_data]);
            $employe_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employe_info) {
                $employe_id = $employe_info['id'];
                error_log("Pointage avec code numérique: $input_data pour employé ID: $employe_id");
            }
        }
        
        // 2. Si pas trouvé, essayer les autres formats
        if (!$employe_id) {
            // Format JSON (nouveau format généré par gestion_employe.php)
            if ($json_data = json_decode($input_data, true)) {
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

        // Empêcher de pointer 2 fois le même type dans la journée
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pointages WHERE employe_id = ? AND type = ? AND DATE(created_at) = ?");
        $stmt->execute([$employe_id, $type, $today]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "⚠️ {$employe_info['prenom']} {$employe_info['nom']} a déjà pointé une $type aujourd'hui.";
        } else {
            // Insertion du pointage
            $stmt = $conn->prepare("INSERT INTO pointages (employe_id, type, created_at, geoloc, methode_pointage) VALUES (?, ?, ?, ?, ?)");
            $methode = preg_match('/^\d{8}$/', $input_data) ? 'code_numerique' : 'qr_code';
            $stmt->execute([$employe_id, $type, $now, $geoloc, $methode]);

            if ($type === 'sortie') {
                // Récupérer l'heure d'entrée
                $stmt = $conn->prepare("SELECT created_at FROM pointages WHERE employe_id = ? AND type = 'entree' AND DATE(created_at) = ? ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$employe_id, $today]);
                $entree = $stmt->fetchColumn();

                if ($entree) {
                    $duree = strtotime($now) - strtotime($entree);
                    $heures = floor($duree / 3600);
                    $minutes = floor(($duree % 3600) / 60);
                    $message = "✅ Sortie de {$employe_info['prenom']} {$employe_info['nom']} enregistrée. Durée travaillée : $heures h $minutes min.";

                    // Détection du retard
                    if (strtotime($entree) > strtotime("$today 09:00:00")) {
                        $message .= " 🚨 Retard détecté à l'entrée.";
                    }

                    // Alerte manager si dépassement 10h
                    if ($duree >= 10 * 3600) {
                        $message .= " 📧 Alerte envoyée au manager.";
                    }
                } else {
                    $message = "⚠️ Sortie enregistrée mais aucune entrée trouvée pour {$employe_info['prenom']} {$employe_info['nom']}.";
                }
            } else {
                $message = "✅ Entrée de {$employe_info['prenom']} {$employe_info['nom']} enregistrée.";
                
                // Afficher le code numérique si disponible
                if (!empty($employe_info['code_numerique'])) {
                    $message .= " (Code: {$employe_info['code_numerique']})";
                }
            }
            
            $pointage_success = true;
        }
    } catch (Exception $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        error_log("Erreur pointage: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Badgeuse QR Code</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Version html5-qrcode stable -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white shadow-lg rounded-xl p-6">
            <h1 class="text-3xl font-bold mb-6 text-center text-indigo-600">📱 Badgeuse QR Code</h1>

            <!-- Message d'état de chargement -->
            <div id="libraryStatus" class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg text-center text-sm">
                <div class="loading-spinner mb-2"></div>
                Chargement de la bibliothèque QR Code...
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 <?= $pointage_success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> rounded-lg text-center">
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
                    <button id="startScan" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                        🎥 Démarrer le scanner
                    </button>
                    <button id="stopScan" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium hidden">
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
                    <strong>Solution :</strong> Utilisez la saisie manuelle avec votre code à 8 chiffres ci-dessous.
                </p>
            </div>

            <!-- Saisie manuelle (fallback) -->
            <div class="border-t pt-6">
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">Saisie manuelle</h3>
                    <p class="text-sm text-blue-700">
                        Utilisez votre <strong>code à 8 chiffres</strong> inscrit sur votre badge
                    </p>
                </div>
                
                <form method="POST" id="manualForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Code employé (8 chiffres) ou ID
                        </label>
                        <input type="text" name="qr_data" id="manualCodeInput"
                               placeholder="Ex: 12345678 ou votre ID employé" 
                               maxlength="8"
                               class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-lg text-center font-mono">
                        <div class="mt-1 text-xs text-gray-500">
                            Votre code à 8 chiffres est inscrit sur votre badge sous le QR code
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
                <p><strong>Code manuel :</strong> Tapez votre code à 8 chiffres (visible sur votre badge)</p>
                <p><strong>ID employé :</strong> En dernier recours, utilisez votre numéro d'employé</p>
                <p class="text-xs">💡 Le code à 8 chiffres est plus sûr et évite les erreurs</p>
            </div>
        </div>
    </div>

    <!-- Formulaire caché pour l'envoi automatique -->
    <form method="POST" id="autoSubmitForm" style="display: none;">
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
            if (scanAttempts < 3) {
                scanAttempts++;
                debugLog(`Tentative ${scanAttempts}/3 de rechargement...`);
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

    // Gestion de la géolocalisation
    function getGeolocation(callback) {
        debugLog('Demande de géolocalisation...');
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lon = position.coords.longitude.toFixed(6);
                    debugLog(`Géolocalisation obtenue: ${lat},${lon}`);
                    callback(`${lat},${lon}`);
                },
                error => {
                    debugLog('Géolocalisation non disponible: ' + error.message);
                    callback(null);
                }
            );
        } else {
            debugLog('Géolocalisation non supportée par le navigateur');
            callback(null);
        }
    }

    // GESTION DU SCANNER QR CORRIGÉE
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
            alert('Bibliothèque QR Code non disponible. Utilisez la saisie manuelle.');
            return;
        }
        
        // Test des permissions caméra
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            debugLog('❌ MediaDevices non supporté');
            alert('Votre navigateur ne supporte pas l\'accès à la caméra.');
            return;
        }
        
        // Demander les permissions
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                debugLog('✅ Permissions caméra accordées');
                // Arrêter le stream test
                stream.getTracks().forEach(track => track.stop());
                
                // Nettoyer le conteneur
                const qrReaderDiv = document.getElementById('qr-reader');
                qrReaderDiv.innerHTML = '';
                
                // Callback de succès
                const onScanSuccess = (decodedText, decodedResult) => {
                    debugLog(`🎯 QR Code détecté: ${decodedText}`);
                    debugLog('Résultat complet:', decodedResult);
                    
                    // Arrêter le scanner immédiatement
                    stopScanning();
                    
                    // Traitement du code scanné
                    processScannedCode(decodedText);
                };
                
                // Callback d'échec (erreurs normales)
                const onScanFailure = (error) => {
                    // Ne pas logger les erreurs normales de scan
                    // debugLog('Erreur scan (normale): ' + error);
                };
                
                try {
                    debugLog('Création de Html5QrcodeScanner...');
                    
                    const config = { 
                        fps: 10,
                        qrbox: { width: 300, height: 300 },
                        aspectRatio: 1.0,
                        disableFlip: false,
                        rememberLastUsedCamera: true,
                        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
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
                    alert('Erreur lors du démarrage du scanner: ' + error.message);
                }
                
            })
            .catch(function(error) {
                debugLog('❌ Erreur permissions caméra: ' + error.message);
                console.error('Erreur permissions caméra:', error);
                alert('Permission caméra refusée. Veuillez autoriser l\'accès à la caméra et rafraîchir la page.');
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

    // Nouvelle fonction pour traiter le code scanné
    function processScannedCode(decodedText) {
        debugLog('=== TRAITEMENT DU CODE SCANNÉ ===');
        debugLog('Code brut: ' + decodedText);
        debugLog('Type: ' + typeof decodedText);
        debugLog('Longueur: ' + decodedText.length);
        
        // Test si c'est du JSON
        try {
            const jsonTest = JSON.parse(decodedText);
            debugLog('JSON valide détecté:', jsonTest);
        } catch (e) {
            debugLog('Pas un JSON: ' + e.message);
        }
        
        // Obtenir la géolocalisation et soumettre
        getGeolocation(function(geoloc) {
            debugLog('Géolocalisation pour envoi: ' + geoloc);
            
            // Remplir le formulaire caché
            document.getElementById('scannedData').value = decodedText;
            document.getElementById('currentAction').value = currentMode;
            document.getElementById('autoGeoloc').value = geoloc || '';
            
            debugLog('Soumission du formulaire automatique...');
            debugLog('- Données: ' + decodedText);
            debugLog('- Action: ' + currentMode);
            debugLog('- Géoloc: ' + (geoloc || 'non disponible'));
            
            // Afficher un message de traitement
            const qrReaderDiv = document.getElementById('qr-reader');
            qrReaderDiv.innerHTML = '<div class="text-center py-4"><div class="loading-spinner mb-2"></div><p>Traitement du code scanné...</p></div>';
            
            // Soumettre le formulaire
            document.getElementById('autoSubmitForm').submit();
        });
    }

    // GESTION DE LA SAISIE MANUELLE
    document.addEventListener('DOMContentLoaded', function() {
        const manualInput = document.getElementById('manualCodeInput');
        
        // Limiter aux chiffres uniquement
        manualInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
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
        
        // Soumission rapide avec Entrée
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (currentMode === 'entree') {
                    document.getElementById('manualEntree').click();
                } else {
                    document.getElementById('manualSortie').click();
                }
            }
        });
    });

    function validateManualCode() {
        const code = document.getElementById('manualCodeInput').value.trim();
        
        debugLog('Validation du code manuel: ' + code);
        
        if (code.length === 0) {
            alert('Veuillez saisir votre code employé');
            return false;
        }
        
        if (!/^\d+$/.test(code)) {
            alert('Le code doit contenir uniquement des chiffres');
            return false;
        }
        
        // Validation spécifique pour les codes à 8 chiffres
        if (code.length === 8) {
            debugLog('Code à 8 chiffres détecté: ' + code);
        } else if (code.length <= 6) {
            debugLog('ID employé détecté: ' + code);
        } else {
            alert('Le code doit faire 8 chiffres (code badge) ou être un ID employé (1-6 chiffres)');
            return false;
        }
        
        return true;
    }

    document.getElementById('manualForm').addEventListener('submit', function(e) {
        if (!validateManualCode()) {
            e.preventDefault();
        } else {
            debugLog('Soumission formulaire manuel validée');
        }
    });

    // Gestion des erreurs de réseau
    window.addEventListener('online', function() {
        debugLog('🟢 Connexion réseau rétablie');
    });

    window.addEventListener('offline', function() {
        debugLog('🔴 Connexion réseau perdue');
    });

    // Auto-refresh après pointage réussi
    <?php if ($pointage_success): ?>
    debugLog('Pointage réussi - rechargement dans 3 secondes');
    setTimeout(function() {
        debugLog('Rechargement de la page après succès');
        window.location.reload();
    }, 3000);
    <?php endif; ?>

    // Détection des formats de QR codes pour debug
    function analyzeQRCode(data) {
        debugLog('=== ANALYSE DU QR CODE ===');
        
        // Test format JSON
        try {
            const json = JSON.parse(data);
            debugLog('Format JSON détecté:');
            debugLog('- ID: ' + (json.id || 'non défini'));
            debugLog('- Code numérique: ' + (json.code_numerique || 'non défini'));
            debugLog('- Nom: ' + (json.nom || 'non défini'));
            return 'json';
        } catch (e) {
            // Pas du JSON
        }
        
        // Test format EMP_
        if (data.startsWith('EMP_')) {
            const id = data.substring(4);
            debugLog('Format EMP_ détecté: ID=' + id);
            return 'emp_legacy';
        }
        
        // Test format numérique pur
        if (/^\d+$/.test(data)) {
            if (data.length === 8) {
                debugLog('Code numérique à 8 chiffres détecté: ' + data);
                return 'code_numerique';
            } else {
                debugLog('ID employé numérique détecté: ' + data);
                return 'id_numerique';
            }
        }
        
        debugLog('Format non reconnu: ' + data);
        return 'unknown';
    }

    // Test de la caméra au démarrage
    function testCamera() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    debugLog('✅ Test caméra: OK');
                    stream.getTracks().forEach(track => track.stop());
                })
                .catch(err => {
                    debugLog('❌ Test caméra: ' + err.message);
                });
        } else {
            debugLog('❌ API MediaDevices non disponible');
        }
    }

    // Lancer le test de caméra
    setTimeout(testCamera, 2000);

    // Raccourci clavier pour activer/désactiver le debug
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            toggleDebug();
        }
    });

    debugLog('🚀 Script initialisé - Version avec debug');
    debugLog('Mode actuel: ' + currentMode);
    debugLog('URL actuelle: ' + window.location.href);
    debugLog('User Agent: ' + navigator.userAgent);
    </script>
</body>
</html>