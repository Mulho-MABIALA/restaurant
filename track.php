<?php
// track.php - Système de tracking des emails
require_once 'config.php';

// Récupérer les paramètres
$campaign_id = isset($_GET['c']) ? (int)$_GET['c'] : 0;
$subscriber_id = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$track_type = isset($_GET['t']) ? $_GET['t'] : '';
$url = isset($_GET['url']) ? $_GET['url'] : '';

// Vérifier les paramètres requis
if (!$campaign_id || !$subscriber_id || !$track_type) {
    http_response_code(400);
    exit('Paramètres manquants');
}

// Obtenir l'IP et l'user agent
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Traiter selon le type de tracking
switch ($track_type) {
    case 'open':
        trackEmailOpen($conn, $campaign_id, $subscriber_id, $ip_address, $user_agent);
        break;
        
    case 'click':
        trackEmailClick($conn, $campaign_id, $subscriber_id, $url, $ip_address, $user_agent);
        break;
        
    default:
        http_response_code(400);
        exit('Type de tracking invalide');
}

/**
 * Tracker l'ouverture d'email
 */
function trackEmailOpen($conn, $campaign_id, $subscriber_id, $ip_address, $user_agent) {
    try {
        // Vérifier si l'ouverture a déjà été trackée
        $stmt = $conn->prepare("
            SELECT id FROM newsletter_tracking 
            WHERE campaign_id = ? AND subscriber_id = ? AND opened_at IS NOT NULL
        ");
        $stmt->execute([$campaign_id, $subscriber_id]);
        
        if (!$stmt->fetch()) {
            // Première ouverture - mettre à jour le tracking
            $stmt = $conn->prepare("
                UPDATE newsletter_tracking 
                SET opened_at = NOW(), user_agent = ?, ip_address = ?
                WHERE campaign_id = ? AND subscriber_id = ?
            ");
            $stmt->execute([$user_agent, $ip_address, $campaign_id, $subscriber_id]);
            
            // Mettre à jour les compteurs de la campagne
            $stmt = $conn->prepare("
                UPDATE newsletter_campaigns 
                SET opened_count = opened_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$campaign_id]);
            
            // Mettre à jour le compteur de l'abonné
            $stmt = $conn->prepare("
                UPDATE newsletter 
                SET total_opens = total_opens + 1, last_activity = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscriber_id]);
        }
        
        // Retourner un pixel transparent
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Pixel GIF 1x1 transparent
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
    } catch (Exception $e) {
        error_log("Erreur trackEmailOpen: " . $e->getMessage());
        http_response_code(500);
    }
}

/**
 * Tracker le clic sur un lien
 */
function trackEmailClick($conn, $campaign_id, $subscriber_id, $url, $ip_address, $user_agent) {
    try {
        // Décoder l'URL
        $url = urldecode($url);
        
        // Valider l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            exit('URL invalide');
        }
        
        // Enregistrer le clic
        $stmt = $conn->prepare("
            UPDATE newsletter_tracking 
            SET clicked_at = COALESCE(clicked_at, NOW()), user_agent = ?, ip_address = ?
            WHERE campaign_id = ? AND subscriber_id = ?
        ");
        $stmt->execute([$user_agent, $ip_address, $campaign_id, $subscriber_id]);
        
        // Mettre à jour les compteurs si c'est le premier clic
        $stmt = $conn->prepare("
            SELECT clicked_at FROM newsletter_tracking 
            WHERE campaign_id = ? AND subscriber_id = ?
        ");
        $stmt->execute([$campaign_id, $subscriber_id]);
        $tracking = $stmt->fetch();
        
        if ($tracking && $tracking['clicked_at']) {
            // Vérifier si c'est le premier clic (dans les 5 dernières secondes)
            $click_time = strtotime($tracking['clicked_at']);
            if (time() - $click_time <= 5) {
                // Premier clic - mettre à jour les compteurs
                $stmt = $conn->prepare("
                    UPDATE newsletter_campaigns 
                    SET clicked_count = clicked_count + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$campaign_id]);
                
                $stmt = $conn->prepare("
                    UPDATE newsletter 
                    SET total_clicks = total_clicks + 1, last_activity = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$subscriber_id]);
            }
        }
        
        // Enregistrer le clic détaillé dans une table séparée (optionnel)
        try {
            $stmt = $conn->prepare("
                INSERT INTO newsletter_click_details (campaign_id, subscriber_id, url, clicked_at, ip_address, user_agent)
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$campaign_id, $subscriber_id, $url, $ip_address, $user_agent]);
        } catch (Exception $e) {
            // Table optionnelle, ne pas échouer si elle n'existe pas
        }
        
        // Rediriger vers l'URL originale
        header('Location: ' . $url, true, 302);
        exit;
        
    } catch (Exception $e) {
        error_log("Erreur trackEmailClick: " . $e->getMessage());
        
        // En cas d'erreur, rediriger quand même vers l'URL
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            header('Location: ' . $url);
        } else {
            http_response_code(500);
            echo 'Erreur de tracking';
        }
    }
}
?>


