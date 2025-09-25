<?php
// includes/newsletter_functions.php

class DatabaseConnection {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $host = 'localhost';
        $dbname = 'newsletter_db';
        $username = 'root';
        $password = '';
        
        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Fonctions pour les templates
function getAllTemplates() {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM newsletter_templates ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTemplateById($id) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM newsletter_templates WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createTemplate($name, $content, $type) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO newsletter_templates (name, content, type, created_at) VALUES (?, ?, ?, NOW())");
    return $stmt->execute([$name, $content, $type]);
}

function updateTemplate($id, $name, $content) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE newsletter_templates SET name = ?, content = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$name, $content, $id]);
}

function deleteTemplate($id) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM newsletter_templates WHERE id = ?");
    return $stmt->execute([$id]);
}

// Fonctions pour les abonnés
function getAllSubscribers() {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM newsletter_subscribers ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllActiveSubscribers() {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM newsletter_subscribers WHERE is_active = 1 ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addSubscriber($email, $name = null) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email, name, is_active, created_at) VALUES (?, ?, 1, NOW())");
    return $stmt->execute([$email, $name]);
}

function getSubscriberStats() {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    $stats = [];
    
    // Total abonnés
    $stmt = $db->query("SELECT COUNT(*) as total FROM newsletter_subscribers");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Abonnés actifs
    $stmt = $db->query("SELECT COUNT(*) as active FROM newsletter_subscribers WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // Abonnés inactifs
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    // Nouveaux abonnés ce mois
    $stmt = $db->query("SELECT COUNT(*) as this_month FROM newsletter_subscribers WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['this_month'];
    
    return $stats;
}

function importSubscribersFromCSV($file_path) {
    $result = ['success' => false, 'imported' => 0, 'duplicates' => 0];
    
    if (!file_exists($file_path)) {
        return $result;
    }
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return $result;
    }
    
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Ignorer la première ligne (en-têtes)
    fgetcsv($handle);
    
    $imported = 0;
    $duplicates = 0;
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) >= 1 && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
            $email = trim($data[0]);
            $name = isset($data[1]) ? trim($data[1]) : null;
            
            $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email, name, is_active, created_at) VALUES (?, ?, 1, NOW())");
            if ($stmt->execute([$email, $name])) {
                if ($stmt->rowCount() > 0) {
                    $imported++;
                } else {
                    $duplicates++;
                }
            }
        }
    }
    
    fclose($handle);
    
    $result['success'] = true;
    $result['imported'] = $imported;
    $result['duplicates'] = $duplicates;
    
    return $result;
}

function toggleSubscriberStatus($id, $status) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE newsletter_subscribers SET is_active = ? WHERE id = ?");
    return $stmt->execute([$status ? 1 : 0, $id]);
}

function deleteSubscriber($id) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
    return $stmt->execute([$id]);
}

// Fonctions d'envoi d'emails
function sendNewsletterNow($template_id, $subject, $custom_content = '') {
    $template = getTemplateById($template_id);
    $subscribers = getAllActiveSubscribers();
    
    if (!$template || empty($subscribers)) {
        return false;
    }
    
    $sent_count = 0;
    
    foreach ($subscribers as $subscriber) {
        if (sendEmailToSubscriber($subscriber, $template, $subject, $custom_content)) {
            $sent_count++;
            logEmailSent($subscriber['id'], $template_id, $subject);
        }
    }
    
    return $sent_count > 0;
}

function sendEmailToSubscriber($subscriber, $template, $subject, $custom_content = '') {
    $replacements = [
        '{SUBSCRIBER_NAME}' => $subscriber['name'] ?: 'Cher abonné',
        '{SUBSCRIBER_EMAIL}' => $subscriber['email'],
        '{UNSUBSCRIBE_LINK}' => generateUnsubscribeLink($subscriber['id']),
        '{COMPANY_NAME}' => 'Votre Entreprise',
        '{CURRENT_DATE}' => date('d/m/Y'),
        '{CUSTOM_CONTENT}' => $custom_content
    ];
    
    $html_content = str_replace(array_keys($replacements), array_values($replacements), $template['content']);
    
    // Configuration de l'email
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: noreply@votreentreprise.com',
        'Reply-To: contact@votreentreprise.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($subscriber['email'], $subject, $html_content, implode("\r\n", $headers));
}

function generateUnsubscribeLink($subscriber_id) {
    $token = md5($subscriber_id . 'unsubscribe_salt');
    return "http://votresite.com/unsubscribe.php?id=$subscriber_id&token=$token";
}

function logEmailSent($subscriber_id, $template_id, $subject) {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO email_logs (subscriber_id, template_id, subject, sent_at, status) VALUES (?, ?, ?, NOW(), 'sent')");
    return $stmt->execute([$subscriber_id, $template_id, $subject]);
}

