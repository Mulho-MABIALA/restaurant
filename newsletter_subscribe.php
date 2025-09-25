<?php
// newsletter_subscribe.php - Version corrigée pour éviter les doublons
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers pour AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Test simple pour vérifier que le fichier fonctionne
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Script newsletter_subscribe.php fonctionne',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    require_once 'config.php';
    
    // Fonction pour nettoyer et valider l'email
    function validateEmail($email) {
        $email = trim(strtolower($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    // Fonction pour obtenir l'IP du client
    function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    // Vérifier si c'est une requête POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Récupérer l'email
    $email = '';
    
    // Essayer JSON d'abord
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $input = json_decode($json_input, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($input['email'])) {
            $email = $input['email'];
        }
    }
    
    // Ensuite essayer $_POST
    if (empty($email) && isset($_POST['email'])) {
        $email = $_POST['email'];
    }
    
    if (empty($email)) {
        throw new Exception('Email requis');
    }
    
    $email = validateEmail($email);
    if (!$email) {
        throw new Exception('Format d\'email invalide');
    }
    
    // Log pour debug
    error_log("Newsletter Debug: Email à traiter = " . $email);
    
    // Démarrer une transaction pour éviter les problèmes de concurrence
    $conn->beginTransaction();
    
    try {
        // Vérifier la structure de la table existante
        $columns = [];
        try {
            $result = $conn->query("DESCRIBE newsletter");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
        } catch (PDOException $e) {
            // Si la table n'existe pas, la créer
            $create_table = "
                CREATE TABLE newsletter (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    statut ENUM('actif', 'inactif') DEFAULT 'actif',
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent TEXT DEFAULT NULL,
                    INDEX idx_email (email),
                    INDEX idx_date_inscription (date_inscription)
                )
            ";
            $conn->exec($create_table);
            $columns = ['id', 'email', 'date_inscription', 'statut', 'ip_address', 'user_agent'];
        }
        
        // Adapter la requête selon les colonnes disponibles
        $hasStatut = in_array('statut', $columns);
        $hasIpAddress = in_array('ip_address', $columns);
        $hasUserAgent = in_array('user_agent', $columns);
        
        // Vérifier si l'email existe déjà avec un verrou exclusif
        $checkQuery = "SELECT id" . ($hasStatut ? ", statut" : "") . " FROM newsletter WHERE email = ? FOR UPDATE";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch();
        
        error_log("Newsletter Debug: Email existant = " . ($existing ? 'OUI (ID: ' . $existing['id'] . ')' : 'NON'));
        
        if ($existing) {
            // L'email existe déjà
            $conn->rollback(); // Annuler la transaction
            
            if ($hasStatut && isset($existing['statut']) && $existing['statut'] === 'inactif') {
                // Si l'email est inactif, le réactiver
                $conn->beginTransaction();
                $updateQuery = "UPDATE newsletter SET statut = 'actif', date_inscription = CURRENT_TIMESTAMP WHERE email = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([$email]);
                $conn->commit();
                
                error_log("Newsletter Debug: Email réactivé = " . $email);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Votre inscription a été réactivée avec succès !'
                ]);
            } else {
                // L'email est déjà actif
                error_log("Newsletter Debug: Email déjà actif = " . $email);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Cette adresse email est déjà inscrite à notre newsletter'
                ]);
            }
            exit;
        }
        
        // L'email n'existe pas, l'ajouter
        error_log("Newsletter Debug: Ajout nouvel email = " . $email);
        
        // Construire la requête d'insertion selon les colonnes disponibles
        $insertFields = ['email'];
        $insertValues = ['?'];
        $insertParams = [$email];
        
        if ($hasStatut) {
            $insertFields[] = 'statut';
            $insertValues[] = '?';
            $insertParams[] = 'actif';
        }
        
        if ($hasIpAddress) {
            $insertFields[] = 'ip_address';
            $insertValues[] = '?';
            $insertParams[] = getClientIP();
        }
        
        if ($hasUserAgent) {
            $insertFields[] = 'user_agent';
            $insertValues[] = '?';
            $insertParams[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        $insertQuery = "INSERT INTO newsletter (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $stmt = $conn->prepare($insertQuery);
        $result = $stmt->execute($insertParams);
        
        if ($result) {
            $conn->commit(); // Valider la transaction
            error_log("Newsletter Debug: Inscription réussie = " . $email);
            
            echo json_encode([
                'success' => true,
                'message' => 'Merci ! Votre inscription a été prise en compte avec succès.'
            ]);
        } else {
            $conn->rollback(); // Annuler en cas d'erreur
            throw new Exception('Erreur lors de l\'insertion en base');
        }
        
    } catch (Exception $e) {
        $conn->rollback(); // Annuler la transaction en cas d'erreur
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Newsletter - Erreur PDO: " . $e->getMessage());
    
    // Vérifier le type d'erreur
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        // Erreur de clé dupliquée
        echo json_encode([
            'success' => false,
            'message' => 'Cette adresse email est déjà inscrite à notre newsletter'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur technique, veuillez réessayer plus tard'
        ]);
    }
} catch (Exception $e) {
    error_log("Newsletter - Erreur: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>