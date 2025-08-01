<?php
// migrate_passwords.php - Script pour migrer les mots de passe en clair vers des hashs sécurisés
// ⚠️ À exécuter UNE SEULE FOIS pour migrer les anciens mots de passe

require_once '../config.php';
require_once 'config_security.php';

echo "🔐 Migration des mots de passe vers un stockage sécurisé\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Créer la table des tentatives si elle n'existe pas
    $conn->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100),
            attempt_time INT NOT NULL,
            INDEX idx_ip_time (ip_address, attempt_time)
        )
    ");
    echo "✅ Table login_attempts créée ou vérifiée\n";

    // Ajouter les colonnes manquantes à la table admin
    $columns = [
        'active' => 'ALTER TABLE admin ADD COLUMN active TINYINT(1) DEFAULT 1',
        'failed_attempts' => 'ALTER TABLE admin ADD COLUMN failed_attempts INT DEFAULT 0',
        'locked_until' => 'ALTER TABLE admin ADD COLUMN locked_until INT NULL'
    ];

    foreach ($columns as $column => $sql) {
        try {
            $conn->exec($sql);
            echo "✅ Colonne '$column' ajoutée\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ️  Colonne '$column' existe déjà\n";
            } else {
                throw $e;
            }
        }
    }

    // Récupérer tous les admins avec mots de passe en clair
    $stmt = $conn->query("SELECT id, username, password, email FROM admin");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($admins)) {
        echo "⚠️  Aucun admin trouvé. Création d'un admin par défaut...\n";
        
        // Créer un admin par défaut
        $defaultPassword = 'Admin123!@#';
        if (createSecureAdmin($conn, 'admin', $defaultPassword, 'admin@tonrestaurant.com')) {
            echo "✅ Admin par défaut créé:\n";
            echo "   Username: admin\n";
            echo "   Password: $defaultPassword\n";
            echo "   Email: admin@tonrestaurant.com\n";
            echo "   ⚠️  CHANGEZ CE MOT DE PASSE IMMÉDIATEMENT !\n\n";
        }
    } else {
        echo "🔄 Migration de " . count($admins) . " admin(s)...\n\n";
        
        foreach ($admins as $admin) {
            // Vérifier si le mot de passe est déjà haché
            if (password_get_info($admin['password'])['algo'] !== null) {
                echo "ℹ️  {$admin['username']}: Mot de passe déjà sécurisé\n";
                continue;
            }

            // Valider le mot de passe actuel
            $passwordErrors = validatePassword($admin['password']);
            if (!empty($passwordErrors)) {
                echo "⚠️  {$admin['username']}: Mot de passe faible détecté!\n";
                echo "   Erreurs: " . implode(', ', $passwordErrors) . "\n";
                echo "   Le mot de passe sera tout de même migré, mais vous devriez le changer.\n";
            }

            // Hacher le mot de passe
            $hashedPassword = password_hash($admin['password'], PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);

            // Mettre à jour dans la base
            $updateStmt = $conn->prepare("UPDATE admin SET password = ?, active = 1, failed_attempts = 0 WHERE id = ?");
            if ($updateStmt->execute([$hashedPassword, $admin['id']])) {
                echo "✅ {$admin['username']}: Mot de passe migré avec succès\n";
            } else {
                echo "❌ {$admin['username']}: Erreur lors de la migration\n";
            }
        }
    }

    // Créer le dossier de logs s'il n'existe pas
    $logDir = '../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
        echo "✅ Dossier de logs créé: $logDir\n";
    }

    // Créer un fichier .htaccess pour protéger les logs
    $htaccessContent = "Order Deny,Allow\nDeny from all";
    file_put_contents($logDir . '/.htaccess', $htaccessContent);
    echo "✅ Protection .htaccess ajoutée aux logs\n";

    // Test de la configuration email
    echo "\n📧 Test de la configuration email...\n";
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Test de connexion sans envoi
        if ($mail->smtpConnect()) {
            echo "✅ Configuration SMTP valide\n";
            $mail->smtpClose();
        }
    } catch (Exception $e) {
        echo "⚠️  Problème avec la configuration SMTP: " . $e->getMessage() . "\n";
        echo "   Vérifiez vos paramètres dans config_security.php\n";
    }

    // Afficher un résumé de sécurité
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🛡️  RÉSUMÉ DE SÉCURITÉ\n";
    echo str_repeat("=", 60) . "\n";
    
    $report = generateSecurityReport($conn);
    echo "📊 Tentatives de connexion échouées (24h): " . $report['failed_attempts_24h'] . "\n";
    echo "🔒 Comptes actuellement verrouillés: " . $report['locked_accounts'] . "\n";
    
    echo "\n🎯 PROCHAINES ÉTAPES RECOMMANDÉES:\n";
    echo "1. Changez tous les mots de passe par défaut\n";
    echo "2. Configurez HTTPS sur votre serveur\n";
    echo "3. Déplacez les credentials SMTP dans un fichier .env\n";
    echo "4. Configurez une sauvegarde automatique de la base\n";
    echo "5. Mettez en place une surveillance des logs\n";
    echo "6. Testez la fonctionnalité 2FA avec un vrai email\n";
    
    echo "\n🔐 SÉCURITÉ ACTIVÉE:\n";
    echo "✅ Mots de passe hachés avec Argon2ID\n";
    echo "✅ Protection CSRF\n";
    echo "✅ Limitation des tentatives de connexion\n";
    echo "✅ Authentification à deux facteurs\n";
    echo "✅ Verrouillage automatique des comptes\n";
    echo "✅ Logs de sécurité\n";
    echo "✅ Headers de sécurité HTTP\n";
    
    echo "\n✨ Migration terminée avec succès !\n";

} catch (Exception $e) {
    echo "❌ Erreur durant la migration: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>