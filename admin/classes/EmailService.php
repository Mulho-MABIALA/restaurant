<?php
/**
 * Service centralisé de gestion des emails
 * Utilise PHPMailer pour l'envoi d'emails
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $config;

    /**
     * Constructeur
     */
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->loadConfig();
        $this->configure();
    }

    /**
     * Charge la configuration depuis les variables d'environnement
     */
    private function loadConfig() {
        // Charger le fichier .env s'il existe
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }

        $this->config = [
            'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'username' => $_ENV['SMTP_USERNAME'] ?? '',
            'password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'port' => $_ENV['SMTP_PORT'] ?? 587,
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
            'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@restaurant.com',
            'from_name' => $_ENV['SMTP_FROM_NAME'] ?? (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho')
        ];

        // Validation
        if (empty($this->config['username']) || empty($this->config['password'])) {
            throw new Exception('SMTP credentials not configured. Please check .env file.');
        }
    }

    /**
     * Configure PHPMailer
     */
    private function configure() {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['username'];
        $this->mailer->Password = $this->config['password'];
        $this->mailer->SMTPSecure = $this->config['encryption'];
        $this->mailer->Port = $this->config['port'];
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
    }

    /**
     * Réinitialise le mailer pour un nouvel envoi
     */
    private function reset() {
        $this->mailer->clearAddresses();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
    }

    /**
     * Envoie un code de vérification 2FA
     */
    public function send2FACode($to, $code, $username) {
        try {
            $this->reset();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Code de vérification - ' . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho');
            $this->mailer->Body = $this->get2FATemplate($code, $username);
            $this->mailer->AltBody = "Votre code de vérification est : $code (valide 5 minutes)";

            $result = $this->mailer->send();

            // Log l'envoi
            $this->logEmail($to, '2FA Code', $result ? 'success' : 'failed');

            return $result;

        } catch (Exception $e) {
            error_log("Email error (2FA): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie une confirmation de commande
     */
    public function sendOrderConfirmation($to, $orderData) {
        try {
            $this->reset();
            $this->mailer->addAddress($to, $orderData['client_name'] ?? '');
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Confirmation de commande #{$orderData['order_id']}";
            $this->mailer->Body = $this->getOrderConfirmationTemplate($orderData);
            $this->mailer->AltBody = $this->getOrderConfirmationText($orderData);

            $result = $this->mailer->send();

            $this->logEmail($to, 'Order Confirmation', $result ? 'success' : 'failed');

            return $result;

        } catch (Exception $e) {
            error_log("Email error (Order): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie une confirmation de réservation
     */
    public function sendReservationConfirmation($to, $reservationData) {
        try {
            $this->reset();
            $this->mailer->addAddress($to, $reservationData['name'] ?? '');
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Confirmation de réservation - ' . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho');
            $this->mailer->Body = $this->getReservationTemplate($reservationData);
            $this->mailer->AltBody = $this->getReservationText($reservationData);

            $result = $this->mailer->send();

            $this->logEmail($to, 'Reservation Confirmation', $result ? 'success' : 'failed');

            return $result;

        } catch (Exception $e) {
            error_log("Email error (Reservation): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie une notification à l'administrateur
     */
    public function sendAdminNotification($subject, $message, $data = []) {
        try {
            $this->reset();

            // Email admin depuis config
            $admin_email = $_ENV['ADMIN_EMAIL'] ?? $this->config['from_email'];
            $this->mailer->addAddress($admin_email);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = "[ADMIN] $subject";
            $this->mailer->Body = $this->getAdminNotificationTemplate($subject, $message, $data);
            $this->mailer->AltBody = strip_tags($message);

            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Email error (Admin Notification): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template HTML pour le code 2FA
     */
    private function get2FATemplate($code, $username) {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
        .header { background: #d1b07c; color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 30px; margin-top: 20px; border-radius: 8px; }
        .code { font-size: 32px; font-weight: bold; color: #d1b07c; text-align: center;
                padding: 20px; background: #f0f0f0; border-radius: 8px; margin: 20px 0;
                letter-spacing: 8px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>" . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho') . "</h1>
        </div>
        <div class="content">
            <h2>Code de vérification</h2>
            <p>Bonjour <strong>{$username}</strong>,</p>
            <p>Votre code de vérification pour accéder à l'espace administrateur est :</p>
            <div class="code">{$code}</div>
            <p><strong>⏰ Ce code expire dans 5 minutes.</strong></p>
            <p>Si vous n'avez pas demandé ce code, veuillez ignorer cet email.</p>
        </div>
        <div class="footer">
            <p>&copy; " . date('Y') . " " . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho') . " - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template HTML pour confirmation de commande
     */
    private function getOrderConfirmationTemplate($data) {
        $items_html = '';
        foreach ($data['items'] as $item) {
            $items_html .= "<tr>
                <td>{$item['name']}</td>
                <td style='text-align: center;'>{$item['quantity']}</td>
                <td style='text-align: right;'>{$item['price']} FCFA</td>
                <td style='text-align: right;'>{$item['total']} FCFA</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d1b07c; color: white; padding: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f4f4f4; font-weight: bold; }
        .total { font-size: 20px; font-weight: bold; color: #d1b07c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Commande confirmée</h1>
        </div>
        <div style="background: white; padding: 30px;">
            <p>Bonjour <strong>{$data['client_name']}</strong>,</p>
            <p>Nous avons bien reçu votre commande <strong>#{$data['order_id']}</strong></p>

            <h3>📦 Détails de la commande :</h3>
            <table>
                <thead>
                    <tr>
                        <th>Plat</th>
                        <th style='text-align: center;'>Qté</th>
                        <th style='text-align: right;'>Prix unit.</th>
                        <th style='text-align: right;'>Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$items_html}
                </tbody>
            </table>

            <p class="total">Total : {$data['total']} FCFA</p>

            <p><strong>📍 Mode de récupération :</strong> {$data['pickup_mode']}</p>
            <p><strong>⏰ Heure estimée :</strong> {$data['estimated_time']}</p>

            <p>Merci de votre confiance !</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template texte pour confirmation de commande
     */
    private function getOrderConfirmationText($data) {
        $items_text = '';
        foreach ($data['items'] as $item) {
            $items_text .= "{$item['name']} x{$item['quantity']} - {$item['total']} FCFA\n";
        }

        return "Commande confirmée #{$data['order_id']}\n\n" .
               "Bonjour {$data['client_name']},\n\n" .
               "Détails :\n{$items_text}\n" .
               "Total : {$data['total']} FCFA\n\n" .
               "Merci !";
    }

    /**
     * Template pour confirmation de réservation
     */
    private function getReservationTemplate($data) {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d1b07c; color: white; padding: 20px; text-align: center; }
        .info { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Réservation confirmée</h1>
        </div>
        <div style="padding: 30px;">
            <p>Bonjour <strong>{$data['name']}</strong>,</p>
            <p>Votre réservation a été confirmée :</p>

            <div class="info">
                <p><strong>📅 Date :</strong> {$data['date']}</p>
                <p><strong>🕒 Heure :</strong> {$data['time']}</p>
                <p><strong>👥 Nombre de personnes :</strong> {$data['people']}</p>
            </div>

            <p>Nous avons hâte de vous accueillir !</p>
            <p>À bientôt,<br>L'équipe " . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho') . "</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template texte pour réservation
     */
    private function getReservationText($data) {
        return "Réservation confirmée\n\n" .
               "Bonjour {$data['name']},\n\n" .
               "Date : {$data['date']}\n" .
               "Heure : {$data['time']}\n" .
               "Personnes : {$data['people']}\n\n" .
               "À bientôt !";
    }

    /**
     * Template pour notification admin
     */
    private function getAdminNotificationTemplate($subject, $message, $data) {
        $data_html = '';
        foreach ($data as $key => $value) {
            $data_html .= "<p><strong>{$key}:</strong> {$value}</p>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: monospace; background: #f4f4f4; color: #333; }
        .container { max-width: 600px; margin: 20px auto; background: white;
                     padding: 20px; border-left: 4px solid #d1b07c; }
        h2 { color: #d1b07c; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔔 {$subject}</h2>
        <div>{$message}</div>
        {$data_html}
        <hr>
        <p style="font-size: 12px; color: #666;">
            Cette notification a été envoyée automatiquement depuis " . (defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant Mulho') . " Admin
        </p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Log les emails envoyés
     */
    private function logEmail($to, $type, $status) {
        $log_file = __DIR__ . '/../../logs/emails.log';
        $log_dir = dirname($log_file);

        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_entry = sprintf(
            "[%s] TO: %s | TYPE: %s | STATUS: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $type,
            strtoupper($status)
        );

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Teste la configuration email
     */
    public function testConnection() {
        try {
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = function($str, $level) {
                echo "Debug level $level; message: $str<br>";
            };

            return $this->mailer->smtpConnect();

        } catch (Exception $e) {
            return false;
        }
    }
}
