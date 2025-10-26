<?php
/**
 * Classe abstraite pour les canaux de notification
 * Base pour Push, SMS, Email
 */

abstract class NotificationChannel {
    protected $conn;
    protected $config;

    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->config = $config;
    }

    /**
     * Envoyer une notification
     *
     * @param array $recipient Destinataire ['email' => '', 'phone' => '', 'name' => '', 'user_id' => null]
     * @param string $type Type de notification
     * @param array $data Données de la notification ['title' => '', 'message' => '', 'data' => []]
     * @return array ['success' => bool, 'message_id' => string, 'error' => string]
     */
    abstract public function send($recipient, $type, $data);

    /**
     * Obtenir le nom du canal
     */
    abstract public function getChannelName();

    /**
     * Vérifier les préférences de l'utilisateur
     */
    protected function checkUserPreferences($recipient, $type) {
        $channel = $this->getChannelName();

        // Récupérer les préférences
        $email = $recipient['email'] ?? null;
        $phone = $recipient['phone'] ?? null;

        if (!$email && !$phone) {
            return false;
        }

        try {
            $sql = "SELECT {$channel}_enabled, {$type}_{$channel} FROM user_notification_preferences WHERE ";

            if ($email) {
                $sql .= "email = ?";
                $param = $email;
            } else {
                $sql .= "telephone = ?";
                $param = $phone;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$param]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prefs) {
                // Pas de préférences = autorisé par défaut
                return true;
            }

            // Vérifier canal activé ET type de notification activé
            $channelEnabled = $prefs["{$channel}_enabled"] ?? 1;
            $typeEnabled = $prefs["{$type}_{$channel}"] ?? 1;

            return $channelEnabled && $typeEnabled;

        } catch (PDOException $e) {
            error_log("Erreur vérification préférences: " . $e->getMessage());
            // En cas d'erreur, autoriser par défaut
            return true;
        }
    }

    /**
     * Logger la notification dans la base de données
     */
    protected function logNotification($recipient, $type, $data, $status, $response = []) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications_log (
                    user_id,
                    recipient_email,
                    recipient_phone,
                    recipient_name,
                    notification_type,
                    channel,
                    title,
                    message,
                    data,
                    status,
                    sent_at,
                    provider,
                    provider_message_id,
                    provider_response,
                    error_message,
                    cost
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $recipient['user_id'] ?? null,
                $recipient['email'] ?? null,
                $recipient['phone'] ?? null,
                $recipient['name'] ?? null,
                $type,
                $this->getChannelName(),
                $data['title'] ?? null,
                $data['message'] ?? '',
                json_encode($data['data'] ?? []),
                $status,
                $status === 'sent' ? date('Y-m-d H:i:s') : null,
                $response['provider'] ?? null,
                $response['message_id'] ?? null,
                !empty($response) ? json_encode($response) : null,
                $response['error'] ?? null,
                $response['cost'] ?? 0
            ]);

            return $this->conn->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erreur log notification: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Remplacer les variables dans un template
     */
    protected function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Récupérer un template
     */
    protected function getTemplate($templateKey) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM notification_templates
                WHERE template_key = ? AND is_active = 1
            ");
            $stmt->execute([$templateKey]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Valider le format d'un numéro de téléphone
     */
    protected function validatePhone($phone) {
        // Nettoyer le numéro
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Format Sénégal: +221XXXXXXXXX ou 7XXXXXXXX
        if (preg_match('/^\+221[7-9]\d{8}$/', $phone)) {
            return $phone;
        }

        if (preg_match('/^[7-9]\d{8}$/', $phone)) {
            return '+221' . $phone;
        }

        return null;
    }

    /**
     * Valider une adresse email
     */
    protected function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
