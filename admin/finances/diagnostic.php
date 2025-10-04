<?php
// diagnostic.php - Fichier de diagnostic pour le module finance
// Placez ce fichier à la racine de votre projet restaurant/

session_start();
$_SESSION['admin_logged_in'] = true; // Pour les tests

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Finance API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            color: #34495e;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .test-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #e0e0e0;
            background: #fafafa;
        }
        .success {
            border-left-color: #27ae60;
            background: #d4edda;
            color: #155724;
        }
        .error {
            border-left-color: #e74c3c;
            background: #f8d7da;
            color: #721c24;
        }
        .warning {
            border-left-color: #f39c12;
            background: #fff3cd;
            color: #856404;
        }
        .info {
            border-left-color: #3498db;
            background: #d1ecf1;
            color: #0c5460;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            margin: 10px 0;
        }
        .code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #e74c3c;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        button:hover {
            background: #2980b9;
        }
        .result-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Complet - Module Finance</h1>

        <!-- Test 1: Configuration PHP -->
        <div class="test-section">
            <h2>1. Configuration PHP et Session</h2>
            <?php
            $php_version = phpversion();
            $version_ok = version_compare($php_version, '7.0.0', '>=');
            ?>
            <div class="test-item <?= $version_ok ? 'success' : 'error' ?>">
                PHP Version: <?= $php_version ?> <?= $version_ok ? '✓' : '✗ (Version 7.0+ requise)' ?>
            </div>
            
            <div class="test-item <?= isset($_SESSION) ? 'success' : 'error' ?>">
                Sessions: <?= isset($_SESSION) ? 'Actives ✓' : 'Non actives ✗' ?>
            </div>
            
            <div class="test-item <?= isset($_SESSION['admin_logged_in']) ? 'success' : 'warning' ?>">
                Session Admin: <?= isset($_SESSION['admin_logged_in']) ? 'Connecté ✓' : 'Non connecté ⚠' ?>
            </div>
            
            <div class="test-item info">
                Session ID: <span class="code"><?= session_id() ?></span>
            </div>
        </div>

        <!-- Test 2: Structure des fichiers -->
        <div class="test-section">
            <h2>2. Vérification de la structure des fichiers</h2>
            <?php
            $files_to_check = [
                'config.php' => 'Configuration base de données',
                'api/finance.php' => 'API Finance',
                'admin/finances/dashboard.php' => 'Dashboard',
                'classes/FinanceHelper.php' => 'Classe FinanceHelper (optionnel)',
                'classes/FacturationManager.php' => 'Classe FacturationManager (optionnel)',
            ];
            
            foreach ($files_to_check as $file => $description) {
                $exists = file_exists($file);
                $class = $exists ? 'success' : (strpos($file, 'classes/') === 0 ? 'warning' : 'error');
                echo "<div class='test-item $class'>";
                echo "$description: ";
                echo $exists ? "✓ Trouvé" : "✗ Non trouvé";
                echo " <span class='code'>$file</span>";
                if ($exists) {
                    $size = filesize($file);
                    echo " (Taille: " . number_format($size) . " octets)";
                }
                echo "</div>";
            }
            ?>
        </div>

        <!-- Test 3: Base de données -->
        <div class="test-section">
            <h2>3. Connexion Base de Données</h2>
            <?php
            $db_ok = false;
            $tables = [];
            
            if (file_exists('config.php')) {
                require_once 'config.php';
                
                try {
                    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $db_ok = true;
                    
                    echo "<div class='test-item success'>✓ Connexion réussie à la base de données</div>";
                    echo "<div class='test-item info'>Base: <span class='code'>$dbname</span> sur <span class='code'>$host</span></div>";
                    
                    // Lister les tables
                    $stmt = $conn->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    echo "<div class='test-item info'>";
                    echo "<strong>Tables trouvées (" . count($tables) . "):</strong><br>";
                    echo "<table>";
                    echo "<tr><th>Nom de la table</th><th>Nombre de lignes</th></tr>";
                    
                    foreach ($tables as $table) {
                        $count_stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
                        $count = $count_stmt->fetchColumn();
                        echo "<tr><td><span class='code'>$table</span></td><td>$count</td></tr>";
                    }
                    echo "</table>";
                    echo "</div>";
                    
                    // Vérifier les tables importantes
                    $required_tables = ['commandes', 'produits', 'clients'];
                    foreach ($required_tables as $table) {
                        $exists = in_array($table, $tables);
                        echo "<div class='test-item " . ($exists ? 'success' : 'warning') . "'>";
                        echo "Table '$table': " . ($exists ? '✓ Existe' : '⚠ N\'existe pas');
                        echo "</div>";
                    }
                    
                } catch (PDOException $e) {
                    echo "<div class='test-item error'>✗ Erreur de connexion: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                echo "<div class='test-item error'>✗ Fichier config.php introuvable</div>";
            }
            ?>
        </div>

        <!-- Test 4: Test de l'API -->
        <div class="test-section">
            <h2>4. Test de l'API Finance</h2>
            
            <div>
                <button onclick="testAPI('dashboard')">Test Dashboard</button>
                <button onclick="testAPI('alertes')">Test Alertes</button>
                <button onclick="testAPI('invalid')">Test Action Invalide</button>
                <button onclick="testDirectPHP()">Test PHP Direct</button>
            </div>
            
            <div id="api-result" class="result-box" style="display:none; margin-top:20px;">
                <h3>Résultat du test:</h3>
                <div id="api-content"></div>
            </div>
        </div>

        <!-- Test 5: Test cURL -->
        <div class="test-section">
            <h2>5. Test avec cURL (côté serveur)</h2>
            <?php
            if (function_exists('curl_init')) {
                echo "<div class='test-item success'>✓ cURL est disponible</div>";
                
                // Construire l'URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['REQUEST_URI']);
                $api_url = "$protocol://$host$path/api/finance.php?action=dashboard&date=" . date('Y-m-d');
                
                echo "<div class='test-item info'>URL de l'API: <span class='code'>$api_url</span></div>";
                
                // Test cURL
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                echo "<div class='test-item " . ($http_code == 200 ? 'success' : 'error') . "'>";
                echo "Code HTTP: $http_code";
                echo "</div>";
                
                if ($error) {
                    echo "<div class='test-item error'>Erreur cURL: $error</div>";
                }
                
                if ($response) {
                    $json = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo "<div class='test-item success'>✓ Réponse JSON valide</div>";
                        echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                    } else {
                        echo "<div class='test-item error'>✗ JSON invalide: " . json_last_error_msg() . "</div>";
                        echo "<div class='test-item info'>Réponse brute (100 premiers caractères):</div>";
                        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
                    }
                } else {
                    echo "<div class='test-item error'>✗ Aucune réponse</div>";
                }
                
            } else {
                echo "<div class='test-item error'>✗ cURL n'est pas disponible</div>";
            }
            ?>
        </div>

        <!-- Test 6: Informations système -->
        <div class="test-section">
            <h2>6. Informations Système</h2>
            <div class="test-item info">
                Serveur: <?= $_SERVER['SERVER_SOFTWARE'] ?>
            </div>
            <div class="test-item info">
                Document Root: <span class="code"><?= $_SERVER['DOCUMENT_ROOT'] ?></span>
            </div>
            <div class="test-item info">
                Script actuel: <span class="code"><?= $_SERVER['SCRIPT_FILENAME'] ?></span>
            </div>
            <div class="test-item info">
                Extensions PHP chargées: <?= count(get_loaded_extensions()) ?> extensions
            </div>
            <?php
            $required_extensions = ['PDO', 'pdo_mysql', 'json', 'session'];
            foreach ($required_extensions as $ext) {
                $loaded = extension_loaded($ext);
                echo "<div class='test-item " . ($loaded ? 'success' : 'error') . "'>";
                echo "Extension '$ext': " . ($loaded ? '✓ Chargée' : '✗ Non chargée');
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <script>
        async function testAPI(action) {
            const resultDiv = document.getElementById('api-result');
            const contentDiv = document.getElementById('api-content');
            
            resultDiv.style.display = 'block';
            contentDiv.innerHTML = '<p>Chargement...</p>';
            
            try {
                const date = new Date().toISOString().split('T')[0];
                const baseUrl = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                const apiUrl = `${baseUrl}/api/finance.php?action=${action}&date=${date}`;
                
                console.log('Test API URL:', apiUrl);
                
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                console.log('Réponse brute:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    contentDiv.innerHTML = `
                        <div style="color: red;">
                            <strong>Erreur de parsing JSON</strong><br>
                            Status HTTP: ${response.status}<br>
                            Réponse brute:<br>
                            <pre style="background: #fff; color: #333; padding: 10px;">${escapeHtml(responseText.substring(0, 500))}</pre>
                        </div>
                    `;
                    return;
                }
                
                contentDiv.innerHTML = `
                    <div style="color: ${response.ok ? 'green' : 'red'};">
                        <strong>Status HTTP:</strong> ${response.status}<br>
                        <strong>Action testée:</strong> ${action}<br>
                        <strong>Réponse JSON:</strong>
                        <pre style="background: #2c3e50; color: #ecf0f1; padding: 10px; margin-top: 10px;">${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
                
            } catch (error) {
                contentDiv.innerHTML = `
                    <div style="color: red;">
                        <strong>Erreur:</strong> ${error.message}<br>
                        Vérifiez la console pour plus de détails.
                    </div>
                `;
                console.error('Erreur complète:', error);
            }
        }
        
        function testDirectPHP() {
            const resultDiv = document.getElementById('api-result');
            const contentDiv = document.getElementById('api-content');
            
            resultDiv.style.display = 'block';
            
            // Test direct sans fetch
            const baseUrl = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const apiUrl = `${baseUrl}/api/finance.php?action=dashboard&date=${new Date().toISOString().split('T')[0]}`;
            
            contentDiv.innerHTML = `
                <div>
                    <strong>Test d'accès direct:</strong><br>
                    Cliquez sur ce lien pour tester directement l'API dans une nouvelle fenêtre:<br>
                    <a href="${apiUrl}" target="_blank" style="color: #3498db;">${apiUrl}</a><br><br>
                    Si vous voyez du JSON, l'API fonctionne.<br>
                    Si vous voyez une erreur PHP, notez-la pour le débogage.
                </div>
            `;
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>