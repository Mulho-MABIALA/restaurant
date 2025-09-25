<?php

function getEmailAnalytics() {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    $analytics = [];
    
    // Emails envoyés par mois
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(sent_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM email_logs 
        WHERE status = 'sent' 
        GROUP BY DATE_FORMAT(sent_at, '%Y-%m') 
        ORDER BY month DESC 
        LIMIT 12
    ");
    $analytics['monthly_sends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques générales
    $stmt = $db->query("SELECT COUNT(*) as total_sent FROM email_logs WHERE status = 'sent'");
    $analytics['total_sent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sent'];
    
    $stmt = $db->query("SELECT COUNT(*) as sent_today FROM email_logs WHERE status = 'sent' AND DATE(sent_at) = CURDATE()");
    $analytics['sent_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['sent_today'];
    
    $stmt = $db->query("SELECT COUNT(*) as sent_this_month FROM email_logs WHERE status = 'sent' AND MONTH(sent_at) = MONTH(NOW()) AND YEAR(sent_at) = YEAR(NOW())");
    $analytics['sent_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['sent_this_month'];
    
    // Templates les plus utilisés
    $stmt = $db->query("
        SELECT 
            nt.name,
            COUNT(el.id) as usage_count
        FROM email_logs el
        JOIN newsletter_templates nt ON el.template_id = nt.id
        WHERE el.status = 'sent'
        GROUP BY el.template_id, nt.name
        ORDER BY usage_count DESC
        LIMIT 5
    ");
    $analytics['popular_templates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $analytics;
}
