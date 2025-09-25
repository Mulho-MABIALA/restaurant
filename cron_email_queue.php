<?php
// cron_email_queue.php - Script cron pour traiter la file d'attente d'emails
// À exécuter toutes les 5-10 minutes via crontab
// Exemple crontab: */5 * * * * /usr/bin/php /path/to/cron_email_queue.php

set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '../includes/email_queue.php';

// Vérifier que le script est exécuté en ligne de commande ou par un cron autorisé
if (php_sapi_name() !== 'cli') {
    // Si exécuté via web, vérifier une clé de sécurité
    $cron_key = $_GET['key'] ?? '';
    $expected_key = $_ENV['CRON_KEY'] ?? 'change_me_secure_key';
    
    if (!hash_equals($expected_key, $cron_key)) {
        http_response_code(403);
        die('Accès non autorisé');
    }
}

// Logger le début du traitement
$start_time = microtime(true);
$log_file = __DIR__ . '/logs/email_queue_' . date('Y-m-d') . '.log';

// Créer le dossier de logs s'il n'existe pas
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    
    // Afficher aussi en ligne de commande
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $message\n";
    }
}

writeLog("=== Début du traitement de la file d'attente ===");

try {
    // Vérifier que la base de données est accessible
    $conn->query('SELECT 1');
    writeLog("Connexion à la base de données : OK");
    
    // Créer l'instance du processeur de file d'attente
    $queue = new EmailQueue(
        $conn,
        50,  // batch_size : 50 emails par lot
        5,   // delay_between_batches : 5 secondes entre les lots
        3    // max_attempts : 3 tentatives maximum
    );
    
    // Obtenir les statistiques avant traitement
    $stats_before = $queue->getQueueStats();
    writeLog("Statistiques avant traitement : " . json_encode($stats_before));
    
    // Traiter la file d'attente
    $process_log = $queue->processQueue();
    
    // Logger les résultats du traitement
    foreach ($process_log as $log_entry) {
        writeLog("QUEUE: $log_entry");
    }
    
    // Obtenir les statistiques après traitement
    $stats_after = $queue->getQueueStats();
    writeLog("Statistiques après traitement : " . json_encode($stats_after));
    
    // Nettoyer les anciens emails (une fois par jour)
    $last_cleanup_file = __DIR__ . '/logs/last_cleanup.txt';
    $last_cleanup = file_exists($last_cleanup_file) ? (int)file_get_contents($last_cleanup_file) : 0;
    
    if (time() - $last_cleanup > 86400) { // 24 heures
        writeLog("Nettoyage des anciens emails...");
        $cleaned = $queue->cleanupQueue(30); // Supprimer les emails de plus de 30 jours
        writeLog("$cleaned anciens emails supprimés");
        file_put_contents($last_cleanup_file, time());
    }
    
    // Vérifier les campagnes bloquées (plus de 2 heures sans progression)
    checkStuckCampaigns($conn);
    
    // Statistiques de performance
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    $memory_usage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    
    writeLog("Traitement terminé en {$execution_time}s (Mémoire: {$memory_usage}MB)");
    
    // Envoyer des alertes si nécessaire
    checkAndSendAlerts($conn, $stats_before, $stats_after);
    
} catch (Exception $e) {
    writeLog("ERREUR CRITIQUE: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    // Envoyer une alerte d'erreur critique
    sendCriticalAlert($e->getMessage());
    
    exit(1);
}

writeLog("=== Fin du traitement ===\n");

/**
 * Vérifier les campagnes qui semblent bloquées
 */
function checkStuckCampaigns($conn) {
    try {
        $stmt = $conn->query("
            SELECT nc.id, nc.name, nc.created_at,
                  COUNT(nq.id) as pending_count,
                  MAX(nq.scheduled_at) as last_scheduled
            FROM newsletter_campaigns nc
            JOIN newsletter_queue nq ON nc.id = nq.campaign_id
            WHERE nc.status = 'sending'
            AND nq.status = 'pending'
            AND nc.created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            GROUP BY nc.id
            HAVING pending_count > 0
        ");
        
        $stuck_campaigns = $stmt->fetchAll();
        
        foreach ($stuck_campaigns as $campaign) {
            writeLog("ALERTE: Campagne bloquée détectée - ID: {$campaign['id']}, Nom: {$campaign['name']}, Emails en attente: {$campaign['pending_count']}");
            
            // Optionnel: Relancer la campagne en modifiant les scheduled_at
            $stmt = $conn->prepare("
                UPDATE newsletter_queue 
                SET scheduled_at = NOW() 
                WHERE campaign_id = ? AND status = 'pending' AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$campaign['id']]);
            
            writeLog("Campagne {$campaign['id']} relancée");
        }
        
    } catch (Exception $e) {
        writeLog("Erreur lors de la vérification des campagnes bloquées: " . $e->getMessage());
    }
}

/**
 * Vérifier et envoyer des alertes
 */
function checkAndSendAlerts($conn, $stats_before, $stats_after) {
    // Alertes pour les échecs
    $failed_count = $stats_after['failed'] ?? 0;
    $total_processed = ($stats_before['pending'] ?? 0) - ($stats_after['pending'] ?? 0);
    
    if ($total_processed > 0) {
        $failure_rate = ($failed_count / ($total_processed + $failed_count)) * 100;
        
        if ($failure_rate > 10) { // Plus de 10% d'échecs
            writeLog("ALERTE: Taux d'échec élevé - {$failure_rate}% ({$failed_count} échecs sur " . ($total_processed + $failed_count) . " tentatives)");
            
            // Envoyer une alerte email aux administrateurs
            sendAdminAlert("Taux d'échec email élevé", 
                          "Le système de newsletter a un taux d'échec de {$failure_rate}%.\n\n" .
                          "Détails:\n" .
                          "- Échecs: $failed_count\n" .
                          "- Total traité: " . ($total_processed + $failed_count) . "\n" .
                          "- Heure: " . date('Y-m-d H:i:s'));
        }
    }
    
    // Alerte pour les files d'attente trop importantes
    $pending_count = $stats_after['pending'] ?? 0;
    if ($pending_count > 10000) {
        writeLog("ALERTE: File d'attente importante - $pending_count emails en attente");
    }
}

/**
 * Envoyer une alerte critique
 */
function sendCriticalAlert($error_message) {
    $admin_email = $_ENV['ADMIN_EMAIL'] ?? null;
    
    if ($admin_email) {
        $subject = "ALERTE CRITIQUE - Système Newsletter";
        $message = "Une erreur critique s'est produite dans le système de newsletter:\n\n";
        $message .= "Erreur: $error_message\n";
        $message .= "Serveur: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "\n";
        $message .= "Heure: " . date('Y-m-d H:i:s') . "\n";
        
        $headers = [
            'From: noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
            'X-Priority: 1 (Highest)',
            'X-MSMail-Priority: High',
            'Importance: High'
        ];
        
        mail($admin_email, $subject, $message, implode("\r\n", $headers));
    }
}

/**
 * Envoyer une alerte aux administrateurs
 */
function sendAdminAlert($subject, $message) {
    $admin_email = $_ENV['ADMIN_EMAIL'] ?? null;
    
    if ($admin_email) {
        $headers = [
            'From: newsletter-system@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        mail($admin_email, "[Newsletter] $subject", $message, implode("\r\n", $headers));
    }
}

/**
 * Fonction pour les tests manuels
 */
function runTests($conn) {
    writeLog("=== Mode Test ===");
    
    // Test de connexion DB
    try {
        $result = $conn->query('SELECT COUNT(*) as count FROM newsletter')->fetch();
        writeLog("Test DB: OK - {$result['count']} abonnés");
    } catch (Exception $e) {
        writeLog("Test DB: ERREUR - " . $e->getMessage());
    }
    
    // Test d'envoi d'email
    $test_email = $_ENV['TEST_EMAIL'] ?? null;
    if ($test_email) {
        $success = mail(
            $test_email,
            'Test Newsletter System',
            'Ceci est un test du système de newsletter.',
            'From: test@' . ($_SERVER['SERVER_NAME'] ?? 'localhost')
        );
        writeLog("Test Email: " . ($success ? "OK" : "ERREUR"));
    }
    
    writeLog("=== Fin du mode Test ===");
}

// Gestion des arguments en ligne de commande
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    switch ($argv[1]) {
        case 'test':
            runTests($conn);
            break;
        case 'stats':
            $queue = new EmailQueue($conn);
            $stats = $queue->getQueueStats();
            writeLog("Statistiques actuelles: " . json_encode($stats, JSON_PRETTY_PRINT));
            break;
        case 'cleanup':
            $queue = new EmailQueue($conn);
            $cleaned = $queue->cleanupQueue(30);
            writeLog("$cleaned anciens emails supprimés");
            break;
        default:
            writeLog("Usage: php cron_email_queue.php [test|stats|cleanup]");
    }
}
?>

<?php
/*
=== INSTRUCTIONS D'INSTALLATION ===

1. Configurer le crontab pour exécuter ce script toutes les 5 minutes :
   ```
   */5 * * * * /usr/bin/php /path/to/your/project/cron_email_queue.php
   ```

2. Ou pour l'exécuter via HTTP (moins sécurisé) :
   ```
   */5 * * * * curl -s "https://yoursite.com/cron_email_queue.php?key=your_secure_key"
   ```

3. Variables d'environnement à configurer dans .env :
   ```
   CRON_KEY=your_secure_random_key
   ADMIN_EMAIL=admin@yoursite.com
   TEST_EMAIL=test@yoursite.com
   APP_SECRET=your_app_secret_for_tokens
   ```

4. Permissions requises :
   - Le dossier logs/ doit être accessible en écriture
   - Le script doit pouvoir se connecter à la base de données
   - La fonction mail() doit être disponible

5. Tests manuels :
   ```
   php cron_email_queue.php test
   php cron_email_queue.php stats
   php cron_email_queue.php cleanup
   ```

6. Monitoring :
   - Vérifiez les logs dans logs/email_queue_YYYY-MM-DD.log
   - Surveillez les alertes envoyées à ADMIN_EMAIL
   - Vérifiez que les campagnes ne restent pas bloquées

7. Optimisations possibles :
   - Ajuster batch_size selon votre serveur
   - Modifier delay_between_batches selon les limites de votre hébergeur
   - Configurer un vrai serveur SMTP au lieu de mail()
*/
?>