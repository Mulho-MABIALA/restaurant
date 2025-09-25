<?php
// includes/email_queue.php
class EmailQueue {
    private $conn;
    private $batch_size;
    private $delay_between_batches;
    private $max_attempts;
    
    public function __construct($database_connection, $batch_size = 50, $delay_seconds = 10, $max_attempts = 3) {
        $this->conn = $database_connection;
        $this->batch_size = $batch_size;
        $this->delay_between_batches = $delay_seconds;
        $this->max_attempts = $max_attempts;
    }
    
    /**
     * Traiter la file d'attente d'emails
     */
    public function processQueue() {
        $log = [];
        $start_time = time();
        
        try {
            // Récupérer les emails en attente
            $pending_emails = $this->getPendingEmails();
            
            if (empty($pending_emails)) {
                $log[] = "Aucun email en attente";
                return $log;
            }
            
            $log[] = "Traitement de " . count($pending_emails) . " emails";
            
            // Traiter par lots
            $batches = array_chunk($pending_emails, $this->batch_size);
            
            foreach ($batches as $batch_index => $batch) {
                $log[] = "Traitement du lot " . ($batch_index + 1) . "/" . count($batches);
                
                foreach ($batch as $email_data) {
                    $result = $this->sendEmail($email_data);
                    
                    if ($result['success']) {
                        $this->markAsSent($email_data['queue_id'], $email_data['campaign_id'], $email_data['subscriber_id']);
                        $log[] = "✓ Email envoyé à " . $email_data['email'];
                    } else {
                        $this->markAsFailed($email_data['queue_id'], $result['error']);
                        $log[] = "✗ Échec pour " . $email_data['email'] . ": " . $result['error'];
                    }
                    
                    // Pause courte entre chaque email pour éviter la surcharge
                    usleep(100000); // 0.1 seconde
                }
                
                // Pause entre les lots
                if ($batch_index < count($batches) - 1) {
                    $log[] = "Pause de {$this->delay_between_batches} secondes...";
                    sleep($this->delay_between_batches);
                }
            }
            
            // Mettre à jour le statut des campagnes terminées
            $this->updateCompletedCampaigns();
            
            $duration = time() - $start_time;
            $log[] = "Traitement terminé en {$duration} secondes";
            
        } catch (Exception $e) {
            $log[] = "Erreur: " . $e->getMessage();
            error_log("Erreur EmailQueue: " . $e->getMessage());
        }
        
        return $log;
    }
    
    /**
     * Récupérer les emails en attente
     */
    private function getPendingEmails() {
        $query = "
            SELECT 
                nq.id as queue_id,
                nq.campaign_id,
                nq.subscriber_id,
                nq.email,
                nq.attempts,
                nc.subject,
                nc.content,
                nc.template_id,
                n.first_name,
                n.last_name,
                n.id as subscriber_db_id
            FROM newsletter_queue nq
            JOIN newsletter_campaigns nc ON nq.campaign_id = nc.id
            JOIN newsletter n ON nq.subscriber_id = n.id
            WHERE nq.status = 'pending'
            AND nq.attempts < ?
            AND (nq.scheduled_at IS NULL OR nq.scheduled_at <= NOW())
            AND nc.status IN ('sending', 'pending')
            ORDER BY nq.scheduled_at ASC, nq.id ASC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->max_attempts, $this->batch_size * 3]); // Plus large pour avoir assez d'emails
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Envoyer un email
     */
    private function sendEmail($email_data) {
        try {
            // Incrémenter le compteur de tentatives
            $this->incrementAttempts($email_data['queue_id']);
            
            // Personnaliser le contenu
            $personalized_content = $this->personalizeContent(
                $email_data['content'],
                $email_data
            );
            
            // Préparer les en-têtes
            $headers = $this->prepareHeaders($email_data);
            
            // Ajouter le tracking
            $tracking_content = $this->addTracking($personalized_content, $email_data);
            
            // Envoyer l'email
            $success = mail(
                $email_data['email'],
                $email_data['subject'],
                $tracking_content,
                implode("\r\n", $headers)
            );
            
            if ($success) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Échec de la fonction mail()'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Personnaliser le contenu de l'email
     */
    private function personalizeContent($content, $email_data) {
        $replacements = [
            '{{first_name}}' => $email_data['first_name'] ?: 'Cher(e) abonné(e)',
            '{{last_name}}' => $email_data['last_name'] ?: '',
            '{{email}}' => $email_data['email'],
            '{{unsubscribe_link}}' => $this->generateUnsubscribeLink($email_data['subscriber_db_id']),
            '{{campaign_id}}' => $email_data['campaign_id'],
            '{{date}}' => date('d/m/Y'),
            '{{year}}' => date('Y')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * Préparer les en-têtes d'email
     */
    private function prepareHeaders($email_data) {
        $from_email = $_ENV['MAIL_FROM'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];
        $from_name = $_ENV['MAIL_FROM_NAME'] ?? 'Newsletter';
        
        return [
            'From: "' . $from_name . '" <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'Return-Path: ' . $from_email,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Newsletter System v1.0',
            'X-Campaign-ID: ' . $email_data['campaign_id'],
            'List-Unsubscribe: <' . $this->generateUnsubscribeLink($email_data['subscriber_db_id']) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click'
        ];
    }
    
    /**
     * Ajouter le tracking à l'email
     */
    private function addTracking($content, $email_data) {
        $base_url = 'https://' . $_SERVER['HTTP_HOST'];
        $tracking_pixel = $base_url . '/newsletter/track.php?c=' . $email_data['campaign_id'] . '&s=' . $email_data['subscriber_id'] . '&t=open';
        
        // Ajouter le pixel de tracking
        $tracking_html = '<img src="' . $tracking_pixel . '" width="1" height="1" style="display:none;" alt="">';
        
        // Ajouter le tracking aux liens
        $content = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function($matches) use ($email_data, $base_url) {
                $original_url = $matches[2];
                
                // Ne pas tracker les liens internes comme unsubscribe
                if (strpos($original_url, 'unsubscribe') !== false || strpos($original_url, '#') === 0) {
                    return $matches[0];
                }
                
                $tracked_url = $base_url . '/newsletter/track.php?c=' . $email_data['campaign_id'] . 
                              '&s=' . $email_data['subscriber_id'] . '&t=click&url=' . urlencode($original_url);
                
                return '<a ' . $matches[1] . 'href="' . $tracked_url . '"' . $matches[3] . '>';
            },
            $content
        );
        
        // Ajouter le pixel à la fin du contenu
        $content .= $tracking_html;
        
        return $content;
    }
    
    /**
     * Générer le lien de désabonnement
     */
    private function generateUnsubscribeLink($subscriber_id) {
        $token = $this->generateUnsubscribeToken($subscriber_id);
        return 'https://' . $_SERVER['HTTP_HOST'] . '/newsletter/unsubscribe.php?token=' . $token;
    }
    
    /**
     * Générer un token sécurisé pour le désabonnement
     */
    private function generateUnsubscribeToken($subscriber_id) {
        $secret = $_ENV['APP_SECRET'] ?? 'default_secret_change_me';
        return hash_hmac('sha256', $subscriber_id, $secret);
    }
    
    /**
     * Marquer un email comme envoyé
     */
    private function markAsSent($queue_id, $campaign_id, $subscriber_id) {
        try {
            // Mettre à jour la file d'attente
            $stmt = $this->conn->prepare("
                UPDATE newsletter_queue 
                SET status = 'sent', sent_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$queue_id]);
            
            // Ajouter au tracking
            $stmt = $this->conn->prepare("
                INSERT INTO newsletter_tracking (campaign_id, subscriber_id, email, sent_at, ip_address)
                SELECT ?, ?, email, NOW(), ?
                FROM newsletter WHERE id = ?
            ");
            $stmt->execute([$campaign_id, $subscriber_id, $_SERVER['REMOTE_ADDR'] ?? '', $subscriber_id]);
            
            // Mettre à jour les compteurs de la campagne
            $stmt = $this->conn->prepare("
                UPDATE newsletter_campaigns 
                SET sent_count = sent_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$campaign_id]);
            
        } catch (PDOException $e) {
            error_log("Erreur markAsSent: " . $e->getMessage());
        }
    }
    
    /**
     * Marquer un email comme échoué
     */
    private function markAsFailed($queue_id, $error_message) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE newsletter_queue 
                SET status = CASE 
                    WHEN attempts >= ? THEN 'failed' 
                    ELSE 'retry' 
                END,
                error_message = ?,
                scheduled_at = CASE 
                    WHEN attempts >= ? THEN NULL 
                    ELSE DATE_ADD(NOW(), INTERVAL (attempts * 15) MINUTE)
                END
                WHERE id = ?
            ");
            $stmt->execute([$this->max_attempts, $error_message, $this->max_attempts, $queue_id]);
            
        } catch (PDOException $e) {
            error_log("Erreur markAsFailed: " . $e->getMessage());
        }
    }
    
    /**
     * Incrémenter le compteur de tentatives
     */
    private function incrementAttempts($queue_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE newsletter_queue 
                SET attempts = attempts + 1 
                WHERE id = ?
            ");
            $stmt->execute([$queue_id]);
            
        } catch (PDOException $e) {
            error_log("Erreur incrementAttempts: " . $e->getMessage());
        }
    }
    
    /**
     * Mettre à jour le statut des campagnes terminées
     */
    private function updateCompletedCampaigns() {
        try {
            $stmt = $this->conn->prepare("
                UPDATE newsletter_campaigns nc
                SET status = 'sent', sent_at = NOW()
                WHERE nc.status = 'sending'
                AND NOT EXISTS (
                    SELECT 1 FROM newsletter_queue nq 
                    WHERE nq.campaign_id = nc.id 
                    AND nq.status IN ('pending', 'retry')
                )
            ");
            $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erreur updateCompletedCampaigns: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir les statistiques de la file d'attente
     */
    public function getQueueStats() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM newsletter_queue 
                GROUP BY status
            ");
            
            $stats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['status']] = $row['count'];
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erreur getQueueStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Nettoyer les anciens emails de la file d'attente
     */
    public function cleanupQueue($days_old = 30) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM newsletter_queue 
                WHERE status IN ('sent', 'failed') 
                AND (sent_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                     OR created_at < DATE_SUB(NOW(), INTERVAL ? DAY))
            ");
            $stmt->execute([$days_old, $days_old]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Erreur cleanupQueue: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Programmer une campagne
     */
    public function scheduleCampaign($campaign_id, $scheduled_datetime) {
        try {
            // Mettre à jour la campagne
            $stmt = $this->conn->prepare("
                UPDATE newsletter_campaigns 
                SET status = 'scheduled', scheduled_at = ? 
                WHERE id = ?
            ");
            $stmt->execute([$scheduled_datetime, $campaign_id]);
            
            // Programmer les emails dans la file d'attente
            $stmt = $this->conn->prepare("
                UPDATE newsletter_queue 
                SET scheduled_at = ? 
                WHERE campaign_id = ? AND status = 'pending'
            ");
            $stmt->execute([$scheduled_datetime, $campaign_id]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur scheduleCampaign: " . $e->getMessage());
            return false;
        }
    }
}

// Fonctions utilitaires pour l'intégration

/**
 * Créer et ajouter une campagne à la file d'attente
 */
function queueCampaign($conn, $campaign_id) {
    try {
        // Récupérer les détails de la campagne
        $stmt = $conn->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $campaign = $stmt->fetch();
        
        if (!$campaign) {
            throw new Exception("Campagne introuvable");
        }
        
        // Récupérer les abonnés actifs
        $stmt = $conn->prepare("
            SELECT id, email 
            FROM newsletter 
            WHERE statut = 'actif'
        ");
        $stmt->execute();
        $subscribers = $stmt->fetchAll();
        
        // Ajouter à la file d'attente
        $stmt = $conn->prepare("
            INSERT INTO newsletter_queue (campaign_id, subscriber_id, email, status, scheduled_at) 
            VALUES (?, ?, ?, 'pending', ?)
        ");
        
        $scheduled_at = $campaign['scheduled_at'] ?? null;
        $count = 0;
        
        foreach ($subscribers as $subscriber) {
            $stmt->execute([
                $campaign_id,
                $subscriber['id'],
                $subscriber['email'],
                $scheduled_at
            ]);
            $count++;
        }
        
        // Mettre à jour le nombre total de destinataires
        $stmt = $conn->prepare("
            UPDATE newsletter_campaigns 
            SET total_recipients = ?, status = CASE 
                WHEN scheduled_at IS NULL THEN 'sending' 
                ELSE 'scheduled' 
            END
            WHERE id = ?
        ");
        $stmt->execute([$count, $campaign_id]);
        
        return $count;
        
    } catch (Exception $e) {
        error_log("Erreur queueCampaign: " . $e->getMessage());
        return false;
    }
}

/**
 * Script cron pour traiter la file d'attente
 * À placer dans un fichier séparé comme cron_email_queue.php
 */
function processEmailQueueCron($conn) {
    $queue = new EmailQueue($conn);
    $log = $queue->processQueue();
    
    // Logger les résultats
    $log_message = "Email Queue Processing: " . date('Y-m-d H:i:s') . "\n";
    $log_message .= implode("\n", $log) . "\n\n";
    
    error_log($log_message, 3, '../logs/email_queue.log');
    
    return $log;
}
?>