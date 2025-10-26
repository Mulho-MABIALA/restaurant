<?php
/**
 * Gestionnaire centralisé de notifications multi-canal
 * Coordonne Push, SMS, Email
 */

require_once __DIR__ . '/PushNotificationService.php';
require_once __DIR__ . '/SMSService.php';

class NotificationManager {
    private $conn;
    private $pushService;
    private $smsService;
    private $emailService;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->pushService = new PushNotificationService($conn);
        $this->smsService = new SMSService($conn);
        // Email service sera ajouté plus tard (déjà existant via EmailService.php)
    }

    /**
     * Envoyer une notification sur tous les canaux activés
     *
     * @param array $recipient ['email' => '', 'phone' => '', 'name' => '', 'user_id' => null]
     * @param string $templateKey Clé du template
     * @param array $variables Variables à remplacer dans le template
     * @param array $channels Canaux à utiliser ['push', 'sms', 'email'] ou 'all'
     * @return array Résultats de chaque canal
     */
    public function send($recipient, $templateKey, $variables = [], $channels = 'all') {
        $results = [];

        // Si 'all', envoyer sur tous les canaux disponibles
        if ($channels === 'all') {
            $channels = ['push', 'sms'];
        }

        // Envoyer sur chaque canal
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'push':
                    if (!empty($recipient['email']) || !empty($recipient['phone'])) {
                        $results['push'] = $this->pushService->sendWithTemplate(
                            $recipient,
                            $templateKey,
                            $variables
                        );
                    }
                    break;

                case 'sms':
                    if (!empty($recipient['phone'])) {
                        $results['sms'] = $this->smsService->sendWithTemplate(
                            $recipient,
                            $templateKey,
                            $variables
                        );
                    }
                    break;

                case 'email':
                    if (!empty($recipient['email'])) {
                        $results['email'] = $this->sendEmail($recipient, $templateKey, $variables);
                    }
                    break;
            }
        }

        return [
            'success' => $this->hasAnySuccess($results),
            'channels' => $results
        ];
    }

    /**
     * Envoyer un email (utilise EmailService existant si disponible)
     */
    private function sendEmail($recipient, $templateKey, $variables) {
        // Vérifier si EmailService existe
        if (!class_exists('EmailService')) {
            if (file_exists(__DIR__ . '/../EmailService.php')) {
                require_once __DIR__ . '/../EmailService.php';
            } else {
                return [
                    'success' => false,
                    'error' => 'EmailService non disponible'
                ];
            }
        }

        try {
            $template = $this->getTemplate($templateKey);

            if (!$template) {
                return ['success' => false, 'error' => 'Template non trouvé'];
            }

            $emailService = new EmailService($this->conn);

            $subject = $this->replaceVariables($template['email_subject'], $variables);
            $body = $this->replaceVariables($template['email_body_html'] ?? $template['email_body_text'], $variables);

            $sent = $emailService->sendEmail(
                $recipient['email'],
                $recipient['name'] ?? 'Client',
                $subject,
                $body
            );

            return [
                'success' => $sent,
                'provider' => 'smtp'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Notification de commande confirmée
     */
    public function notifyOrderConfirmed($order) {
        $recipient = [
            'email' => $order['client_email'] ?? $order['email'] ?? null,
            'phone' => $order['client_telephone'] ?? $order['telephone'] ?? null,
            'name' => $order['client_nom'] ?? $order['nom_client'] ?? 'Client'
        ];

        $variables = [
            'nom' => $recipient['name'],
            'numero_commande' => $order['numero_commande'] ?? $order['id'],
            'montant' => number_format($order['total'], 0, ',', ' '),
            'temps_preparation' => '20-30'
        ];

        return $this->send($recipient, 'commande_confirmee', $variables);
    }

    /**
     * Notification de commande prête
     */
    public function notifyOrderReady($order) {
        $recipient = [
            'email' => $order['client_email'] ?? $order['email'] ?? null,
            'phone' => $order['client_telephone'] ?? $order['telephone'] ?? null,
            'name' => $order['client_nom'] ?? $order['nom_client'] ?? 'Client'
        ];

        $variables = [
            'nom' => $recipient['name'],
            'numero_commande' => $order['numero_commande'] ?? $order['id']
        ];

        return $this->send($recipient, 'commande_prete', $variables);
    }

    /**
     * Notification de commande en livraison
     */
    public function notifyOrderInDelivery($order, $estimatedTime = '20 min') {
        $recipient = [
            'email' => $order['client_email'] ?? $order['email'] ?? null,
            'phone' => $order['client_telephone'] ?? $order['telephone'] ?? null,
            'name' => $order['client_nom'] ?? $order['nom_client'] ?? 'Client'
        ];

        $variables = [
            'nom' => $recipient['name'],
            'numero_commande' => $order['numero_commande'] ?? $order['id'],
            'heure_arrivee' => $estimatedTime
        ];

        return $this->send($recipient, 'commande_en_livraison', $variables);
    }

    /**
     * Notification de réservation confirmée
     */
    public function notifyReservationConfirmed($reservation) {
        $recipient = [
            'email' => $reservation['email'] ?? null,
            'phone' => $reservation['telephone'] ?? null,
            'name' => $reservation['nom'] ?? 'Client'
        ];

        $dateReservation = date('d/m/Y', strtotime($reservation['date_reservation']));
        $heureReservation = date('H:i', strtotime($reservation['heure_reservation']));

        $variables = [
            'nom' => $recipient['name'],
            'nombre_personnes' => $reservation['nombre_personnes'],
            'date' => $dateReservation,
            'heure' => $heureReservation,
            'num_table' => $reservation['num_table'] ?? 'TBD'
        ];

        return $this->send($recipient, 'reservation_confirmee', $variables);
    }

    /**
     * Programmer un rappel de réservation
     */
    public function scheduleReservationReminder($reservation, $hoursBefor = 2) {
        $reminderTime = date('Y-m-d H:i:s', strtotime($reservation['date_reservation'] . ' ' . $reservation['heure_reservation'] . ' -' . $hoursBefor . ' hours'));

        $recipient = [
            'email' => $reservation['email'] ?? null,
            'phone' => $reservation['telephone'] ?? null,
            'name' => $reservation['nom'] ?? 'Client'
        ];

        $variables = [
            'nom' => $recipient['name'],
            'heure' => date('H:i', strtotime($reservation['heure_reservation'])),
            'nombre_personnes' => $reservation['nombre_personnes']
        ];

        return $this->scheduleNotification(
            $recipient,
            'rappel_reservation_2h',
            $variables,
            $reminderTime,
            'reservation',
            $reservation['id']
        );
    }

    /**
     * Notification de paiement réussi
     */
    public function notifyPaymentSuccess($payment, $order) {
        $recipient = [
            'email' => $order['client_email'] ?? $order['email'] ?? null,
            'phone' => $order['client_telephone'] ?? $order['telephone'] ?? null,
            'name' => $order['client_nom'] ?? $order['nom_client'] ?? 'Client'
        ];

        $providerNames = [
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'paydunya' => 'Carte bancaire'
        ];

        $variables = [
            'nom' => $recipient['name'],
            'montant' => number_format($payment['montant'], 0, ',', ' '),
            'provider' => $providerNames[$payment['provider']] ?? $payment['provider'],
            'numero_commande' => $order['numero_commande'] ?? $order['id']
        ];

        return $this->send($recipient, 'paiement_reussi', $variables);
    }

    /**
     * Programmer une notification
     */
    public function scheduleNotification($recipient, $templateKey, $variables, $scheduledFor, $referenceType = null, $referenceId = null) {
        try {
            $template = $this->getTemplate($templateKey);

            if (!$template) {
                return ['success' => false, 'error' => 'Template non trouvé'];
            }

            $stmt = $this->conn->prepare("
                INSERT INTO scheduled_notifications (
                    recipient_email,
                    recipient_phone,
                    recipient_name,
                    notification_type,
                    template_id,
                    title,
                    message,
                    data,
                    scheduled_for,
                    reference_type,
                    reference_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $recipient['email'] ?? null,
                $recipient['phone'] ?? null,
                $recipient['name'] ?? null,
                $template['notification_type'],
                $template['id'],
                $this->replaceVariables($template['push_title'], $variables),
                $this->replaceVariables($template['push_body'], $variables),
                json_encode($variables),
                $scheduledFor,
                $referenceType,
                $referenceId
            ]);

            return [
                'success' => true,
                'scheduled_id' => $this->conn->lastInsertId()
            ];

        } catch (PDOException $e) {
            error_log("Erreur programmation notification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Traiter les notifications programmées (à exécuter via CRON)
     */
    public function processPendingNotifications() {
        try {
            // Récupérer les notifications à envoyer
            $stmt = $this->conn->query("
                SELECT * FROM scheduled_notifications
                WHERE status = 'pending'
                AND scheduled_for <= NOW()
                ORDER BY scheduled_for ASC
                LIMIT 50
            ");

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $processed = 0;

            foreach ($notifications as $notif) {
                $recipient = [
                    'email' => $notif['recipient_email'],
                    'phone' => $notif['recipient_phone'],
                    'name' => $notif['recipient_name']
                ];

                $data = [
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'data' => json_decode($notif['data'], true) ?? []
                ];

                // Envoyer selon le canal
                $result = null;
                switch ($notif['channel']) {
                    case 'push':
                        $result = $this->pushService->send($recipient, $notif['notification_type'], $data);
                        break;
                    case 'sms':
                        $result = $this->smsService->send($recipient, $notif['notification_type'], $data);
                        break;
                    case 'all':
                        $result = $this->send($recipient, $notif['template_id'], json_decode($notif['data'], true) ?? []);
                        break;
                }

                // Mettre à jour le statut
                $status = ($result && $result['success']) ? 'sent' : 'failed';
                $errorMsg = (!$result || !$result['success']) ? ($result['error'] ?? 'Erreur inconnue') : null;

                $updateStmt = $this->conn->prepare("
                    UPDATE scheduled_notifications
                    SET status = ?,
                        sent_at = NOW(),
                        error_message = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([$status, $errorMsg, $notif['id']]);
                $processed++;
            }

            return [
                'success' => true,
                'processed' => $processed
            ];

        } catch (PDOException $e) {
            error_log("Erreur traitement notifications: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtenir les statistiques du jour
     */
    public function getTodayStats() {
        try {
            $stmt = $this->conn->query("SELECT * FROM v_notification_stats_today");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Helper methods
     */
    private function getTemplate($templateKey) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM notification_templates
                WHERE template_key = ? AND is_active = 1
            ");
            $stmt->execute([$templateKey]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    private function hasAnySuccess($results) {
        foreach ($results as $result) {
            if (isset($result['success']) && $result['success']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Accès aux services individuels
     */
    public function getPushService() {
        return $this->pushService;
    }

    public function getSMSService() {
        return $this->smsService;
    }
}
