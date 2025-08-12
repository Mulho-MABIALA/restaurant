<?php
require_once '../config.php';
session_start();

$message = '';
$employe_info = null;
$pointage_success = false;

// Traitement du pointage via QR Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'], $_POST['action'])) {
    $qr_data = trim($_POST['qr_data']);
    $type = $_POST['action']; // 'entree' ou 'sortie'
    $geoloc = $_POST['geoloc'] ?? null;
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    try {
        // Décoder le QR code pour obtenir l'ID employé
        // Format attendu: "EMP_12345" ou directement l'ID numérique
        $employe_id = null;
        if (strpos($qr_data, 'EMP_') === 0) {
            $employe_id = (int) substr($qr_data, 4);
        } elseif (is_numeric($qr_data)) {
            $employe_id = (int) $qr_data;
        }

        if (!$employe_id) {
            throw new Exception("QR Code invalide");
        }

        // Vérifier si l'employé existe
        $stmt = $conn->prepare("SELECT id, nom, departement FROM employes WHERE id = ?");
        $stmt->execute([$employe_id]);
        $employe_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employe_info) {
            throw new Exception("Employé non trouvé dans le système");
        }

        // Empêcher de pointer 2 fois le même type dans la journée
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pointages WHERE employe_id = ? AND type = ? AND DATE(created_at) = ?");
        $stmt->execute([$employe_id, $type, $today]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "⚠️ {$employe_info['nom']} a déjà pointé une $type aujourd'hui.";
        } else {
            // Insertion du pointage
            $stmt = $conn->prepare("INSERT INTO pointages (employe_id, type, created_at, geoloc) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employe_id, $type, $now, $geoloc]);

            if ($type === 'sortie') {
                // Récupérer l'heure d'entrée
                $stmt = $conn->prepare("SELECT created_at FROM pointages WHERE employe_id = ? AND type = 'entree' AND DATE(created_at) = ? ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$employe_id, $today]);
                $entree = $stmt->fetchColumn();

                if ($entree) {
                    $duree = strtotime($now) - strtotime($entree);
                    $heures = floor($duree / 3600);
                    $minutes = floor(($duree % 3600) / 60);
                    $message = "✅ Sortie de {$employe_info['nom']} enregistrée. Durée travaillée : $heures h $minutes min.";

                    // Détection du retard
                    if (strtotime($entree) > strtotime("$today 09:00:00")) {
                        $message .= " 🚨 Retard détecté à l'entrée.";
                    }

                    // Alerte manager si dépassement 10h
                    if ($duree >= 10 * 3600) {
                        @mail("manager@example.com", "Dépassement horaire", "{$employe_info['nom']} a travaillé plus de 10h aujourd'hui.");
                        $message .= " 📧 Alerte envoyée au manager.";
                    }
                } else {
                    $message = "⚠️ Impossible de calculer la durée : aucune entrée trouvée pour {$employe_info['nom']}.";
                }
            } else {
                $message = "✅ Entrée de {$employe_info['nom']} enregistrée.";
            }
            
            $pointage_success = true;
        }
    } catch (Exception $e) {
        $message = "❌ Erreur : " . $e->getMessage();
        error_log("Erreur pointage QR: " . $e->getMessage());
    } catch (PDOException $e) {
        $message = "❌ Erreur base de données : " . $e->getMessage();
        error_log("Erreur pointage QR pour employé: " . $e->getMessage());
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
    <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white shadow-lg rounded-xl p-6">
            <h1 class="text-3xl font-bold mb-6 text-center text-indigo-600">📱 Badgeuse QR Code</h1>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 <?= $pointage_success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> rounded-lg text-center">
                    <?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($employe_info && is_array($employe_info)): ?>
                        <div class="mt-2 text-sm">
                            <strong>Employé:</strong> <?= htmlspecialchars((string)($employe_info['nom'] ?? 'Inconnu'), ENT_QUOTES, 'UTF-8') ?> 
                            (<?= htmlspecialchars((string)($employe_info['departement'] ?? 'Non défini'), ENT_QUOTES, 'UTF-8') ?>)
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
            <div class="mb-6">
                <div class="text-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Scannez votre badge QR</h2>
                    <p class="text-sm text-gray-600">Placez votre code QR devant la caméra</p>
                </div>
                
                <div id="qr-reader" class="border-2 border-dashed border-gray-300 rounded-lg p-4"></div>
                
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

            <!-- Saisie manuelle (fallback) -->
            <div class="border-t pt-6">
                <details class="group">
                    <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800">
                        🔧 Saisie manuelle (en cas de problème avec la caméra)
                    </summary>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <form method="POST" id="manualForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code employé</label>
                                <input type="text" name="qr_data" placeholder="Saisissez votre code employé" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <button type="submit" name="action" value="entree" id="manualEntree" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg mb-2">
                                📥 Pointer Entrée
                            </button>
                            <button type="submit" name="action" value="sortie" id="manualSortie" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg hidden">
                                📤 Pointer Sortie
                            </button>
                            <input type="hidden" name="geoloc" id="manualGeoloc">
                        </form>
                    </div>
                </details>
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
        <div class="mt-6 bg-blue-50 rounded-lg p-4 text-sm text-blue-800">
            <h3 class="font-semibold mb-2">💡 Comment utiliser le système:</h3>
            <ul class="space-y-1 text-xs">
                <li>• Sélectionnez le mode (Entrée/Sortie)</li>
                <li>• Cliquez sur "Démarrer le scanner"</li>
                <li>• Présentez votre badge QR devant la caméra</li>
                <li>• Le pointage s'effectue automatiquement</li>
            </ul>
        </div>
    </div>

    <!-- Formulaire caché pour l'envoi automatique -->
    <form method="POST" id="autoSubmitForm" style="display: none;">
        <input type="hidden" name="qr_data" id="scannedData">
        <input type="hidden" name="action" id="currentAction" value="entree">
        <input type="hidden" name="geoloc" id="autoGeoloc">
    </form>

    <script>
    let html5QrcodeScanner;
    let currentMode = 'entree';
    let isScanning = false;

    // Gestion des modes entrée/sortie
    document.getElementById('entreeBtn').addEventListener('click', function() {
        currentMode = 'entree';
        updateModeUI();
    });

    document.getElementById('sortieBtn').addEventListener('click', function() {
        currentMode = 'sortie';
        updateModeUI();
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
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lon = position.coords.longitude.toFixed(6);
                    callback(`${lat},${lon}`);
                },
                error => {
                    console.log('Géolocalisation non disponible:', error);
                    callback(null);
                }
            );
        } else {
            callback(null);
        }
    }

    // Vérifier si la bibliothèque est chargée
    function checkLibraryLoaded() {
        return typeof Html5QrcodeScanner !== 'undefined' && typeof Html5Qrcode !== 'undefined';
    }

    // Scanner QR
    document.getElementById('startScan').addEventListener('click', function() {
        console.log('Bouton scanner cliqué');
        
        if (!checkLibraryLoaded()) {
            alert('Erreur: Bibliothèque QR Code non chargée. Veuillez rafraîchir la page.');
            return;
        }
        
        if (!isScanning) {
            startScanning();
        }
    });

    document.getElementById('stopScan').addEventListener('click', function() {
        if (isScanning) {
            stopScanning();
        }
    });

    function startScanning() {
        console.log('Démarrage du scanner...');
        
        // Vider le conteneur QR reader
        document.getElementById('qr-reader').innerHTML = '';
        
        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            console.log(`QR Code scanné: ${decodedText}`);
            
            // Arrêter le scanner
            stopScanning();
            
            // Obtenir la géolocalisation et envoyer le formulaire
            getGeolocation(function(geoloc) {
                document.getElementById('scannedData').value = decodedText;
                document.getElementById('currentAction').value = currentMode;
                document.getElementById('autoGeoloc').value = geoloc || '';
                document.getElementById('autoSubmitForm').submit();
            });
        };

        const qrCodeErrorCallback = (error) => {
            // Ignorer les erreurs de scan (normal quand aucun QR n'est détecté)
            console.debug('Scanner QR:', error);
        };

        try {
            const config = { 
                fps: 10, 
                qrbox: { width: 250, height: 250 },
                rememberLastUsedCamera: true,
                showTorchButtonIfSupported: true,
                showZoomSliderIfSupported: true,
                defaultZoomValueIfSupported: 2
            };

            html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", config, false);
            html5QrcodeScanner.render(qrCodeSuccessCallback, qrCodeErrorCallback);
            
            isScanning = true;
            document.getElementById('startScan').classList.add('hidden');
            document.getElementById('stopScan').classList.remove('hidden');
            
            console.log('Scanner démarré avec succès');
            
        } catch (error) {
            console.error('Erreur lors du démarrage du scanner:', error);
            alert('Erreur lors du démarrage du scanner: ' + error.message);
            
            // Essayer avec l'ancienne méthode
            tryAlternativeScanner();
        }
    }

    function tryAlternativeScanner() {
        console.log('Tentative avec scanner alternatif...');
        
        try {
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    const cameraId = devices[0].id;
                    
                    const html5QrCode = new Html5Qrcode("qr-reader");
                    html5QrCode.start(
                        cameraId,
                        {
                            fps: 10,
                            qrbox: { width: 250, height: 250 }
                        },
                        (decodedText, decodedResult) => {
                            console.log(`QR Code scanné (méthode alternative): ${decodedText}`);
                            html5QrCode.stop();
                            
                            getGeolocation(function(geoloc) {
                                document.getElementById('scannedData').value = decodedText;
                                document.getElementById('currentAction').value = currentMode;
                                document.getElementById('autoGeoloc').value = geoloc || '';
                                document.getElementById('autoSubmitForm').submit();
                            });
                        },
                        (errorMessage) => {
                            console.debug('Erreur scan alternatif:', errorMessage);
                        }
                    ).then(() => {
                        console.log('Scanner alternatif démarré');
                        isScanning = true;
                        document.getElementById('startScan').classList.add('hidden');
                        document.getElementById('stopScan').classList.remove('hidden');
                        html5QrcodeScanner = html5QrCode; // Pour le stop
                    }).catch(err => {
                        console.error('Erreur scanner alternatif:', err);
                        alert('Impossible de démarrer la caméra. Vérifiez les permissions.');
                    });
                } else {
                    alert('Aucune caméra détectée sur cet appareil.');
                }
            }).catch(err => {
                console.error('Erreur détection caméra:', err);
                alert('Erreur lors de la détection des caméras.');
            });
            
        } catch (error) {
            console.error('Erreur scanner alternatif:', error);
            alert('Scanner QR non supporté sur ce navigateur.');
        }
    }

    function stopScanning() {
        console.log('Arrêt du scanner...');
        
        if (html5QrcodeScanner) {
            if (typeof html5QrcodeScanner.clear === 'function') {
                html5QrcodeScanner.clear().catch(error => {
                    console.error("Erreur lors de l'arrêt du scanner:", error);
                });
            } else if (typeof html5QrcodeScanner.stop === 'function') {
                html5QrcodeScanner.stop().catch(error => {
                    console.error("Erreur lors de l'arrêt du scanner:", error);
                });
            }
        }
        
        isScanning = false;
        document.getElementById('startScan').classList.remove('hidden');
        document.getElementById('stopScan').classList.add('hidden');
        
        // Nettoyer le conteneur
        setTimeout(() => {
            document.getElementById('qr-reader').innerHTML = '';
        }, 1000);
    }

    // Géolocalisation pour le formulaire manuel
    getGeolocation(function(geoloc) {
        if (geoloc) {
            document.getElementById('manualGeoloc').value = geoloc;
        }
    });

    // Auto-refresh de la page après un pointage réussi (optionnel)
    <?php if ($pointage_success): ?>
    setTimeout(function() {
        // Masquer le message après 5 secondes
        const messageDiv = document.querySelector('.bg-green-100');
        if (messageDiv) {
            messageDiv.style.transition = 'opacity 0.5s';
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 500);
        }
    }, 5000);
    <?php endif; ?>
    </script>
</body>
</html>