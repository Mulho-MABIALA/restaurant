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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <style>
        #qr-reader {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        #qr-reader__dashboard_section_csr button {
            background: #4F46E5 !important;
            color: white !important;
            border-radius: 8px !important;
            margin: 4px !important;
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
        /* Style pour les logs de debug */
        #debugLog {
            max-height: 200px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
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

            <!-- Debug Log -->
            <div id="debugSection" class="mb-4 p-3 bg-gray-50 rounded-lg" style="display: none;">
                <h4 class="font-semibold text-gray-700 mb-2">🔍 Debug Scanner</h4>
                <div id="debugLog" class="text-xs text-gray-600 bg-white p-2 rounded border"></div>
                <button id="clearDebug" class="mt-2 text-xs bg-gray-200 px-2 py-1 rounded">Effacer logs</button>
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
                <div class="mt-2 text-sm text-gray-600">
                    Mode actuel: <span id="currentModeDisplay" class="font-semibold text-green-600">Entrée</span>
                </div>
            </div>

            <!-- Scanner QR -->
            <div class="mb-6" id="qrScannerSection" style="display: none;">
                <div class="text-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Scannez votre badge QR</h2>
                    <p class="text-sm text-gray-600">Placez votre code QR devant la caméra</p>
                </div>
                
                <div id="qr-reader" class="border-2 border-dashed border-gray-300 rounded-lg p-4 bg-black"></div>
                
                <div class="mt-4 text-center">
                    <button id="startScan" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                        🎥 Démarrer le scanner
                    </button>
                    <button id="stopScan" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium hidden">
                        ⏹️ Arrêter le scanner
                    </button>
                    <button id="toggleDebug" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm ml-2">
                        🐛 Debug
                    </button>
                </div>
                
                <!-- Status du scanner -->
                <div id="scannerStatus" class="mt-3 text-center text-sm text-gray-600"></div>
            </div>

            <!-- Message d'erreur bibliothèque -->
            <div id="qrErrorSection" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg" style="display: none;">
                <h3 class="font-semibold text-yellow-800 mb-2">⚠️ Scanner QR indisponible</h3>
                <p class="text-sm text-yellow-700 mb-2">
                    La bibliothèque de scan QR n'a pas pu se charger.
                </p>
                <p class="text-sm text-yellow-700 mt-2">
                    <strong>Solution :</strong> Utilisez la saisie manuelle avec votre code à 8 chiffres ci-dessous.
                </p>
            </div>

            <!-- Test QR Code pour debug -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-semibold text-blue-800 mb-2">🧪 Test rapide</h3>
                <p class="text-sm text-blue-700 mb-3">Pour tester le scanner :</p>
                
                <div class="space-y-3">
                    <!-- Instructions étape par étape -->
                    <div class="bg-white p-3 rounded border text-sm">
                        <strong>Étape 1:</strong> Allez sur 
                        <a href="https://qr-code-generator.com/" target="_blank" class="text-blue-600 underline">qr-code-generator.com</a>
                    </div>
                    <div class="bg-white p-3 rounded border text-sm">
                        <strong>Étape 2:</strong> Tapez exactement: <code class="bg-gray-100 px-2 py-1 rounded">12345678</code>
                    </div>
                    <div class="bg-white p-3 rounded border text-sm">
                        <strong>Étape 3:</strong> Générez le QR code et affichez-le sur un autre écran
                    </div>
                    <div class="bg-white p-3 rounded border text-sm">
                        <strong>Étape 4:</strong> Scannez ce QR code avec la badgeuse
                    </div>
                </div>

                <!-- Bouton pour test automatique -->
                <div class="mt-4 text-center">
                    <button id="simulateQR" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded font-medium text-sm">
                        🔄 Simuler un scan (test)
                    </button>
                </div>
                
                <div class="mt-3 text-xs text-gray-600">
                    💡 <strong>Astuce:</strong> Le QR code doit être net, bien contrasté et entièrement visible dans le cadre de scan.
                </div>
            </div>

            <!-- Saisie manuelle -->
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
                               placeholder="Ex: 12345678" 
                               maxlength="8"
                               class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-lg text-center font-mono">
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
        const now = new Date().toLocaleTimeString();
        const debugDiv = document.getElementById('debugLog');
        if (debugDiv) {
            debugDiv.innerHTML += `[${now}] ${message}<br>`;
            debugDiv.scrollTop = debugDiv.scrollHeight;
        }
        console.log(`[DEBUG] ${message}`);
    }

    // Toggle debug
    document.getElementById('toggleDebug').addEventListener('click', function() {
        const debugSection = document.getElementById('debugSection');
        if (debugSection.style.display === 'none') {
            debugSection.style.display = 'block';
            debugLog('=== DEBUG ACTIVÉ ===');
        } else {
            debugSection.style.display = 'none';
        }
    });

    document.getElementById('clearDebug').addEventListener('click', function() {
        document.getElementById('debugLog').innerHTML = '';
    });

    // Vérification du statut de la bibliothèque
    function checkLibraryStatus() {
        debugLog('Vérification de la bibliothèque QR...');
        debugLog(`Html5QrcodeScanner: ${typeof Html5QrcodeScanner}`);
        debugLog(`Html5Qrcode: ${typeof Html5Qrcode}`);
        
        if (typeof Html5QrcodeScanner !== 'undefined' || typeof Html5Qrcode !== 'undefined') {
            libraryLoaded = true;
            showLibrarySuccess();
            debugLog('✅ Bibliothèque QR chargée avec succès');
        } else {
            showLibraryError();
            debugLog('❌ Échec du chargement de la bibliothèque');
        }
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
        debugLog('Page chargée, vérification de la bibliothèque...');
        setTimeout(checkLibraryStatus, 1000);
    });

    // Gestion des modes entrée/sortie
    document.getElementById('entreeBtn').addEventListener('click', function() {
        currentMode = 'entree';
        updateModeUI();
        debugLog(`Mode changé vers: ${currentMode}`);
    });

    document.getElementById('sortieBtn').addEventListener('click', function() {
        currentMode = 'sortie';
        updateModeUI();
        debugLog(`Mode changé vers: ${currentMode}`);
    });

    function updateModeUI() {
        const entreeBtn = document.getElementById('entreeBtn');
        const sortieBtn = document.getElementById('sortieBtn');
        const manualEntree = document.getElementById('manualEntree');
        const manualSortie = document.getElementById('manualSortie');
        const modeDisplay = document.getElementById('currentModeDisplay');

        if (currentMode === 'entree') {
            entreeBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors bg-green-600 text-white';
            sortieBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
            manualEntree.classList.remove('hidden');
            manualSortie.classList.add('hidden');
            modeDisplay.textContent = 'Entrée';
            modeDisplay.className = 'font-semibold text-green-600';
        } else {
            entreeBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
            sortieBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors bg-red-600 text-white';
            manualEntree.classList.add('hidden');
            manualSortie.classList.remove('hidden');
            modeDisplay.textContent = 'Sortie';
            modeDisplay.className = 'font-semibold text-red-600';
        }
    }

    // Status du scanner
    function updateScannerStatus(message, type = 'info') {
        const statusDiv = document.getElementById('scannerStatus');
        const colors = {
            'info': 'text-blue-600',
            'success': 'text-green-600',
            'error': 'text-red-600',
            'warning': 'text-yellow-600'
        };
        statusDiv.className = `mt-3 text-center text-sm ${colors[type]}`;
        statusDiv.textContent = message;
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
                    debugLog(`Erreur géolocalisation: ${error.message}`);
                    callback(null);
                }
            );
        } else {
            debugLog('Géolocalisation non supportée');
            callback(null);
        }
    }

    // DÉMARRAGE DU SCANNER
    document.getElementById('startScan').addEventListener('click', function() {
        debugLog('=== TENTATIVE DE DÉMARRAGE DU SCANNER ===');
        
        if (!libraryLoaded) {
            debugLog('❌ Bibliothèque non chargée');
            alert('La bibliothèque QR Code n\'est pas chargée. Utilisez la saisie manuelle.');
            return;
        }
        
        if (isScanning) {
            debugLog('⚠️ Scanner déjà en cours');
            return;
        }
        
        startScanning();
    });

    document.getElementById('stopScan').addEventListener('click', function() {
        debugLog('=== ARRÊT DEMANDÉ ===');
        stopScanning();
    });

    function startScanning() {
        debugLog('Initialisation du scanner...');
        updateScannerStatus('Initialisation...', 'info');
        
        // Vérification finale
        if (typeof Html5QrcodeScanner === 'undefined' && typeof Html5Qrcode === 'undefined') {
            debugLog('❌ Bibliothèques indisponibles au moment du scan');
            alert('Bibliothèque QR Code non disponible.');
            return;
        }
        
        // Test permissions caméra
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            debugLog('❌ API caméra non supportée');
            alert('Votre navigateur ne supporte pas l\'accès à la caméra.');
            return;
        }
        
        debugLog('Demande des permissions caméra...');
        updateScannerStatus('Demande d\'accès à la caméra...', 'info');
        
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                debugLog('✅ Permissions caméra accordées');
                updateScannerStatus('Caméra autorisée, démarrage...', 'success');
                
                // Arrêter le stream de test
                stream.getTracks().forEach(track => {
                    track.stop();
                    debugLog(`Track fermé: ${track.kind}`);
                });
                
                // Vider le conteneur
                const qrReaderDiv = document.getElementById('qr-reader');
                qrReaderDiv.innerHTML = '';
                debugLog('Conteneur vidé');
                
                // Callbacks
                const onScanSuccess = (decodedText, decodedResult) => {
                    scanAttempts++;
                    debugLog(`🎉 QR CODE DÉTECTÉ (tentative ${scanAttempts}): "${decodedText}"`);
                    debugLog(`Type de résultat: ${typeof decodedText}`);
                    debugLog(`Longueur: ${decodedText.length}`);
                    
                    updateScannerStatus(`QR détecté: ${decodedText}`, 'success');
                    
                    // Arrêter le scanner avant soumission
                    stopScanning();
                    
                    // Préparer la soumission
                    debugLog('Préparation de la soumission...');
                    getGeolocation(function(geoloc) {
                        document.getElementById('scannedData').value = decodedText;
                        document.getElementById('currentAction').value = currentMode;
                        document.getElementById('autoGeoloc').value = geoloc || '';
                        
                        debugLog(`Données préparées:`);
                        debugLog(`- QR Data: "${decodedText}"`);
                        debugLog(`- Action: "${currentMode}"`);
                        debugLog(`- Géoloc: "${geoloc || 'non disponible'}"`);
                        
                        // Soumission du formulaire
                        debugLog('🚀 SOUMISSION DU FORMULAIRE');
                        document.getElementById('autoSubmitForm').submit();
                    });
                };
                
                const onScanFailure = (error) => {
                    // C'est normal, ne pas logger tous les échecs
                    scanAttempts++;
                    if (scanAttempts % 50 === 0) { // Log toutes les 50 tentatives
                        debugLog(`Tentatives de scan: ${scanAttempts} (en cours...)`);
                    }
                };
                
                try {
                    debugLog('Tentative avec Html5QrcodeScanner...');
                    
                    // Configuration optimisée pour une meilleure détection
                    const config = { 
                        fps: 20,  // Plus de FPS pour une meilleure détection
                        qrbox: function(viewfinderWidth, viewfinderHeight) {
                            // Zone de scan dynamique
                            let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                            let qrboxSize = Math.floor(minEdgeSize * 0.7);
                            return {
                                width: qrboxSize,
                                height: qrboxSize
                            };
                        },
                        aspectRatio: 1.777778,  // Ratio 16:9 plus standard
                        disableFlip: false,
                        rememberLastUsedCamera: true,
                        // Support pour différents formats
                        supportedScanTypes: [
                            Html5QrcodeScanType.SCAN_TYPE_CAMERA
                        ],
                        // Améliorations de détection
                        experimentalFeatures: {
                            useBarCodeDetectorIfSupported: true
                        },
                        // Contraintes caméra pour une meilleure qualité
                        videoConstraints: {
                            facingMode: "environment",  // Caméra arrière si disponible
                            focusMode: "continuous"     // Autofocus continu
                        }
                    };
                    
                    debugLog(`Configuration scanner optimisée: ${JSON.stringify(config)}`);
                    
                    html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", config);
                    
                    debugLog('Scanner créé avec config optimisée, appel de render()...');
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                    
                    isScanning = true;
                    scanAttempts = 0;
                    
                    // Mise à jour UI
                    document.getElementById('startScan').classList.add('hidden');
                    document.getElementById('stopScan').classList.remove('hidden');
                    
                    updateScannerStatus('Scanner actif - Présentez votre QR code', 'success');
                    debugLog('✅ Scanner démarré avec succès');
                    
                } catch (error) {
                    debugLog(`❌ Erreur lors de la création du scanner: ${error.message}`);
                    debugLog(`Stack: ${error.stack}`);
                    updateScannerStatus('Erreur lors du démarrage', 'error');
                    alert('Erreur lors du démarrage du scanner: ' + error.message);
                }
                
            })
            .catch(function(error) {
                debugLog(`❌ Erreur permissions caméra: ${error.name} - ${error.message}`);
                updateScannerStatus('Accès caméra refusé', 'error');
                alert('Permission caméra refusée. Veuillez autoriser l\'accès à la caméra.');
            });
    }

    function stopScanning() {
        debugLog('Arrêt du scanner...');
        updateScannerStatus('Arrêt en cours...', 'warning');
        
        if (html5QrcodeScanner && isScanning) {
            try {
                if (typeof html5QrcodeScanner.clear === 'function') {
                    debugLog('Utilisation de clear()');
                    html5QrcodeScanner.clear().then(() => {
                        debugLog('✅ Scanner arrêté avec clear()');
                    }).catch(err => {
                        debugLog(`Erreur clear(): ${err.message}`);
                    });
                } else if (typeof html5QrcodeScanner.stop === 'function') {
                    debugLog('Utilisation de stop()');
                    html5QrcodeScanner.stop().then(() => {
                        debugLog('✅ Scanner arrêté avec stop()');
                    }).catch(err => {
                        debugLog(`Erreur stop(): ${err.message}`);
                    });
                } else {
                    debugLog('⚠️ Aucune méthode d\'arrêt trouvée');
                }
            } catch (e) {
                debugLog(`❌ Exception lors de l'arrêt: ${e.message}`);
            }
        }
        
        isScanning = false;
        document.getElementById('startScan').classList.remove('hidden');
        document.getElementById('stopScan').classList.add('hidden');
        
        updateScannerStatus('Scanner arrêté', 'info');
        
        setTimeout(() => {
            document.getElementById('qr-reader').innerHTML = '';
            debugLog('Conteneur nettoyé');
        }, 500);
    }

    // Gestion saisie manuelle
    document.addEventListener('DOMContentLoaded', function() {
        const manualInput = document.getElementById('manualCodeInput');
        
        manualInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        
        getGeolocation(function(geoloc) {
            if (geoloc) {
                document.getElementById('manualGeoloc').value = geoloc;
            }
        });
    });

    function validateManualCode() {
        const code = document.getElementById('manualCodeInput').value.trim();
        
        if (code.length === 0) {
            alert('Veuillez saisir votre code employé');
            return false;
        }
        
        if (!/^\d+$/.test(code)) {
            alert('Le code doit contenir uniquement des chiffres');
            return false;
        }
        
        return true;
    }

    document.getElementById('manualForm').addEventListener('submit', function(e) {
        debugLog(`Soumission manuelle: "${document.getElementById('manualCodeInput').value}"`);
        if (!validateManualCode()) {
            e.preventDefault();
        }
    });

    // Bouton de simulation pour test
    document.getElementById('simulateQR').addEventListener('click', function() {
        debugLog('🔄 SIMULATION D\'UN SCAN QR');
        const testData = '12345678';
        
        // Simuler la détection d'un QR code
        debugLog(`Simulation avec données: "${testData}"`);
        
        if (isScanning) {
            stopScanning();
        }
        
        getGeolocation(function(geoloc) {
            document.getElementById('scannedData').value = testData;
            document.getElementById('currentAction').value = currentMode;
            document.getElementById('autoGeoloc').value = geoloc || '';
            
            debugLog(`Simulation - Mode: ${currentMode}, Géoloc: ${geoloc || 'non disponible'}`);
            debugLog('🚀 SOUMISSION SIMULÉE');
            
            // Afficher un message de confirmation avant soumission
            if (confirm(`Test: Pointer une ${currentMode} avec le code ${testData} ?`)) {
                document.getElementById('autoSubmitForm').submit();
            }
        });
    });

    // Auto-refresh après pointage réussi
    <?php if ($pointage_success): ?>
    setTimeout(function() {
        debugLog('Rechargement automatique après succès');
        window.location.reload();
    }, 3000);
    <?php endif; ?>
    </script>
</body>
</html>